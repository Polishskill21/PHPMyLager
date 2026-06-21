<?php

namespace App\Http\Controllers\Orders;

use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\Products\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\Customers\Customer;
use App\Support\DomainCache;
use Illuminate\Contracts\View\View;


class OrderController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /orders
     */
    public function index(): JsonResponse
    {
        $orders = DomainCache::remember(
            DomainCache::ORDERS,
            'orders:index',
            fn () => Order::with(['items.product', 'customer'])->get()
                          ->map(fn (Order $o) => $this->formatOrder($o))
        );

        return $this->ok($orders);
    }

    /**
     * GET /orders/page
     * One load-more chunk of the orders list, with server-side search/sort.
     */
    public function page(Request $request): JsonResponse
    {
        return $this->renderListChunk(
            DomainCache::ORDERS,
            $this->browseQuery($request),
            $this->isDefaultListView($request),
            fn (Order $o) => $this->formatRow($o),
            'partials.rows.orders-row',
            $request,
        );
    }

    /**
     * Server-rendered /orders page: the cached default-view first chunk plus the
     * cached grand total (sum of all order line values).
     */
    public function indexView(Request $request): View
    {
        $chunk = $this->firstChunk($request);

        return view('orders', [
            'firstRows'      => $chunk['rows'],
            'meta'           => $chunk['meta'],
            'ordersTotalEur' => DomainCache::remember(
                DomainCache::ORDERS,
                'orders:total-eur',
                fn () => (float) OrderItem::query()
                    ->selectRaw('COALESCE(SUM(' . OrderItem::COL_AUF_MENGE . ' * ' . OrderItem::COL_KAUF_PREIS . '), 0) as total')
                    ->value('total')
            ),
        ]);
    }

    /** First chunk for the server-rendered /orders page. */
    private function firstChunk(Request $request): array
    {
        return $this->listChunkData(
            DomainCache::ORDERS,
            $this->browseQuery($request),
            $this->isDefaultListView($request),
            fn (Order $o) => $this->formatRow($o),
            $request,
        );
    }

    /**
     * GET /orders/{order}
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['items.product', 'customer']);

        return $this->ok($this->formatOrder($order));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /orders
     * Creates the order header, attaches all line-items, snapshots each
     * product's current selling price and decrements stock.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->storeRules(),
            $this->customMessages()
        );

        try {
            $order = DB::transaction(function () use ($validated): Order {
                $order = Order::create([
                    Order::COL_AUF_DAT    => $validated[Order::COL_AUF_DAT],
                    Order::COL_F_KD_NR    => $validated[Order::COL_F_KD_NR],
                    Order::COL_AUF_TERMIN => $validated[Order::COL_AUF_TERMIN],
                ]);

                foreach ($validated['items'] as $item) {
                    $product = Product::where(Product::COL_ID, $item[OrderItem::COL_F_ARTIKEL_NR])
                                      ->lockForUpdate()
                                      ->firstOrFail();

                    $this->ensureSufficientStock($product, $item[OrderItem::COL_AUF_MENGE]);

                    $order->items()->create([
                        OrderItem::COL_F_ARTIKEL_NR => $product->{Product::COL_ID},
                        OrderItem::COL_AUF_MENGE    => $item[OrderItem::COL_AUF_MENGE],
                        OrderItem::COL_KAUF_PREIS   => $product->{Product::COL_VK_PREIS},
                    ]);

                    $product->decrement(Product::COL_BESTAND, $item[OrderItem::COL_AUF_MENGE]);
                }

                return $order->load('items.product');
            });
            DomainCache::flush(DomainCache::ORDERS, DomainCache::PRODUCTS);

            return $this->created($this->formatOrder($order), 'Order created successfully.');

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * PUT /orders/{order}
     * Full replacement: send the complete list of items; omitted items are
     * deleted and their stock is restored.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate(
            $this->updateRules(),
            $this->customMessages()
        );

        try {
            $order = DB::transaction(function () use ($validated, $order): Order {
                $order->update([
                    Order::COL_AUF_DAT    => $validated[Order::COL_AUF_DAT],
                    Order::COL_F_KD_NR    => $validated[Order::COL_F_KD_NR],
                    Order::COL_AUF_TERMIN => $validated[Order::COL_AUF_TERMIN],
                ]);

                $currentItems    = $order->items->keyBy(OrderItem::COL_ID);
                $submittedPosNrs = [];

                foreach ($validated['items'] as $submittedItem) {
                    $artikelNr = (int) $submittedItem[OrderItem::COL_F_ARTIKEL_NR];
                    $newMenge  = (int) $submittedItem[OrderItem::COL_AUF_MENGE];

                    if (!empty($submittedItem['pAufPosNr'])) {
                        // ── Existing line: adjust quantity ──────────────────
                        $posNr             = (int) $submittedItem[OrderItem::COL_ID];
                        $submittedPosNrs[] = $posNr;

                        $existingItem = $currentItems->get($posNr);

                        if (!$existingItem) {
                            // Business-logic error, not a user input error — 422
                            throw ValidationException::withMessages([
                                'items' => "Order item {$posNr} does not belong to order {$order->{Order::COL_ID}}.",
                            ]);
                        }

                        if ((int) $existingItem->{OrderItem::COL_F_ARTIKEL_NR} !== $artikelNr) {
                            throw ValidationException::withMessages([
                                'items' => "Cannot change Product ID on {$posNr}. Remove the item and re-add it with the new product.",
                            ]);
                        }

                        $diff = $newMenge - (int) $existingItem->{OrderItem::COL_AUF_MENGE};

                        if ($diff !== 0) {
                            $product = Product::withTrashed()
                                              ->where(Product::COL_ID, $artikelNr)
                                              ->lockForUpdate()
                                              ->firstOrFail();

                            if ($product->trashed()) {
                                throw ValidationException::withMessages([
                                    'items' => "Cannot alter the quantity of product {$product->{Product::COL_ID}} ({$product->{Product::COL_NAME}}) because it has been discontinued.",
                                ]);
                            }

                            if ($diff > 0) {
                                $this->ensureSufficientStock($product, $diff);
                                $product->decrement(Product::COL_BESTAND, $diff);
                            } else {
                                $product->increment(Product::COL_BESTAND, abs($diff));
                            }
                        }

                        $existingItem->update([OrderItem::COL_AUF_MENGE => $newMenge]);

                    } else {
                        // ── New line ────────────────────────────────────────
                        $product = Product::where(Product::COL_ID, $artikelNr)
                                          ->lockForUpdate()
                                          ->firstOrFail();

                        $this->ensureSufficientStock($product, $newMenge);

                        $order->items()->create([
                            OrderItem::COL_F_ARTIKEL_NR => $artikelNr,
                            OrderItem::COL_AUF_MENGE    => $newMenge,
                            OrderItem::COL_KAUF_PREIS   => $product->{Product::COL_VK_PREIS},
                        ]);

                        $product->decrement(Product::COL_BESTAND, $newMenge);
                    }
                }

                // ── Remove lines omitted from the request ───────────────────
                foreach ($currentItems as $posNr => $item) {
                    if (!in_array($posNr, $submittedPosNrs, true)) {
                        Product::withTrashed()
                               ->where(Product::COL_ID, $item->{OrderItem::COL_F_ARTIKEL_NR})
                               ->increment(Product::COL_BESTAND, $item->{OrderItem::COL_AUF_MENGE});
                        $item->delete();
                    }
                }

                return $order->fresh(['items.product']);
            });
            DomainCache::flush(DomainCache::ORDERS, DomainCache::PRODUCTS);

            return $this->ok($this->formatOrder($order), 'Order updated successfully.');

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * DELETE /orders/{order}
     * Restores stock for every line-item, then removes all positions and the
     * order header.
     */
    public function destroy(Order $order): JsonResponse
    {
        try {
            DB::transaction(function () use ($order): void {
                $order->load('items');

                foreach ($order->items as $item) {
                    Product::withTrashed()
                           ->where(Product::COL_ID, $item->{OrderItem::COL_F_ARTIKEL_NR})
                           ->increment(Product::COL_BESTAND, $item->{OrderItem::COL_AUF_MENGE});
                }

                $order->items()->delete();
                $order->delete();
            });
            DomainCache::flush(DomainCache::ORDERS, DomainCache::PRODUCTS);

            return $this->noContent();
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Browse / list helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Base browse query with eager loads, server-side search and sort.
     * Derived columns (customer name, item count, total) sort via correlated
     * subqueries so pagination stays a simple LIMIT/OFFSET.
     */
    private function browseQuery(Request $request): Builder
    {
        $query = Order::query()->with(['customer', 'items']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where(Order::COL_ID, $search)
                  ->orWhere(Order::COL_F_KD_NR, $search)
                  ->orWhereHas('customer', fn (Builder $c) => $c->where(Customer::COL_NAME, 'like', "%{$search}%"));
            });
        }

        $dir = $this->sortDirection($request);
        switch ($request->query('sort')) {
            case 'id':       $query->orderBy(Order::COL_ID, $dir); break;
            case 'created':  $query->orderBy(Order::COL_AUF_DAT, $dir); break;
            case 'delivery': $query->orderBy(Order::COL_AUF_TERMIN, $dir); break;
            case 'customer':
                $query->orderBy(
                    Customer::query()->select(Customer::COL_NAME)
                        ->whereColumn(Customer::COL_ID, Order::TABLE . '.' . Order::COL_F_KD_NR),
                    $dir
                );
                break;
            case 'items':
                $query->orderBy(
                    OrderItem::query()->selectRaw('COUNT(*)')
                        ->whereColumn(OrderItem::COL_F_AUF_NR, Order::TABLE . '.' . Order::COL_ID),
                    $dir
                );
                break;
            case 'total':
                $query->orderBy(
                    OrderItem::query()->selectRaw('COALESCE(SUM(' . OrderItem::COL_AUF_MENGE . ' * ' . OrderItem::COL_KAUF_PREIS . '), 0)')
                        ->whereColumn(OrderItem::COL_F_AUF_NR, Order::TABLE . '.' . Order::COL_ID),
                    $dir
                );
                break;
            default:
                $query->orderByDesc(Order::COL_AUF_DAT);
        }

        return $query;
    }

    /** Map an order to the array consumed by partials/rows/orders-row. */
    private function formatRow(Order $order): array
    {
        $items = $order->items;
        $total = $items->sum(fn (OrderItem $i) => (int) $i->{OrderItem::COL_AUF_MENGE} * (float) $i->{OrderItem::COL_KAUF_PREIS});

        return [
            'id'         => $order->{Order::COL_ID},
            'customer'   => $order->customer?->{Customer::COL_NAME} ?: 'Unknown customer',
            'created'    => $order->{Order::COL_AUF_DAT} ? substr((string) $order->{Order::COL_AUF_DAT}, 0, 10) : '',
            'delivery'   => $order->{Order::COL_AUF_TERMIN} ? substr((string) $order->{Order::COL_AUF_TERMIN}, 0, 10) : '',
            'item_count' => $items->count(),
            'total'      => round($total, 2),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validation rule sets
    // ─────────────────────────────────────────────────────────────────────────

    private function storeRules(): array
    {
        return [
            Order::COL_AUF_DAT                     => 'required|date',
            Order::COL_F_KD_NR                     => ['required', 'integer', Rule::exists(Customer::TABLE, Customer::COL_ID)->whereNull('deleted_at')],
            Order::COL_AUF_TERMIN                  => 'required|date|after_or_equal:' . Order::COL_AUF_DAT,
            'items'                                => 'required|array|min:1',
            'items.*.' . OrderItem::COL_F_ARTIKEL_NR => ['required', 'integer', Rule::exists(Product::TABLE, Product::COL_ID)->whereNull('deleted_at')],
            'items.*.' . OrderItem::COL_AUF_MENGE    => 'required|integer|min:1',
        ];
    }

    private function updateRules(): array
    {
        return [
            Order::COL_AUF_DAT                       => 'required|date',
            Order::COL_F_KD_NR                       => ['required', 'integer', Rule::exists(Customer::TABLE, Customer::COL_ID)->whereNull('deleted_at')],
            Order::COL_AUF_TERMIN                    => 'required|date|after_or_equal:' . Order::COL_AUF_DAT,
            'items'                                  => 'required|array|min:1',
            'items.*.' . OrderItem::COL_ID           => 'nullable|integer',
            'items.*.' . OrderItem::COL_F_ARTIKEL_NR => 'required|integer|exists:' . Product::TABLE . ',' . Product::COL_ID,
            'items.*.' . OrderItem::COL_AUF_MENGE    => 'required|integer|min:1',
        ];
    }

    private function customMessages(): array
    {
        return [
            Order::COL_F_KD_NR . '.exists'                       => 'The selected customer does not exist.',
            Order::COL_AUF_TERMIN . '.after_or_equal'            => 'The delivery date must be on or after the order date.',
            'items.required'                                     => 'At least one order item is required.',
            'items.min'                                          => 'At least one order item is required.',
            'items.*.' . OrderItem::COL_F_ARTIKEL_NR . '.exists' => 'The product selected is invalid or has been discontinued.',
            'items.*.' . OrderItem::COL_AUF_MENGE . '.min'       => 'The quantity for the item must be at least 1.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Response shape:
     * {
     *   "order_info":  { pAufNr, aufDat, aufTermin, fKdNr, customer_name, is_customer_deleted },
     *   "items":       [ { pAufPosNr, fArtikelNr, bezeichnung, aufMenge,
     *                      kaufPreis, line_total, is_discontinued } ],
     *   "order_total": <total units>,
     *   "preis_total": <sum kaufPreis × aufMenge>
     * }
     */
    private function formatOrder(Order $order): array
    {
        $items = $order->items;
        $customer = $order->customer;

        $orderTotal = $items->sum(OrderItem::COL_AUF_MENGE);
        $preisTotal = $items->sum(
            fn (OrderItem $item) => (float) $item->{OrderItem::COL_KAUF_PREIS} * (int) $item->{OrderItem::COL_AUF_MENGE}
        );

        return [
            'order_info' => [
                Order::COL_ID         => $order->{Order::COL_ID},
                Order::COL_AUF_DAT    => $order->{Order::COL_AUF_DAT},
                Order::COL_AUF_TERMIN => $order->{Order::COL_AUF_TERMIN},
                Order::COL_F_KD_NR    => $order->{Order::COL_F_KD_NR},
                'customer_name'       => $customer?->{Customer::COL_NAME},
                'is_customer_deleted' => $customer?->trashed() ?? false,
            ],
            'items' => $items->map(fn (OrderItem $item) => [
                OrderItem::COL_ID           => $item->{OrderItem::COL_ID},
                OrderItem::COL_F_ARTIKEL_NR => $item->{OrderItem::COL_F_ARTIKEL_NR},
                Product::COL_NAME           => $item->product?->{Product::COL_NAME},
                OrderItem::COL_AUF_MENGE    => $item->{OrderItem::COL_AUF_MENGE},
                OrderItem::COL_KAUF_PREIS   => $item->{OrderItem::COL_KAUF_PREIS},
                'line_total'                => round((float) $item->{OrderItem::COL_KAUF_PREIS} * $item->{OrderItem::COL_AUF_MENGE}, 2),
                'is_discontinued'           => $item->product?->trashed() ?? false,
            ])->values(),
            'order_total' => $orderTotal,
            'preis_total' => round($preisTotal, 2),
        ];
    }

    /**
     * Throws a ValidationException (HTTP 422) when bestand < requested qty.
     * ValidationException is re-thrown by callers so Laravel renders it
     * with the standard errors envelope.
     */
    private function ensureSufficientStock(Product $product, int $requested): void
    {
        if ($product->{Product::COL_BESTAND} < $requested) {
            throw ValidationException::withMessages([
                'items' => sprintf(
                    'Insufficient stock for product %s (%s). Available: %d, requested: %d.',
                    $product->{Product::COL_ID},
                    $product->{Product::COL_NAME},
                    $product->{Product::COL_BESTAND},
                    $requested
                ),
            ]);
        }
    }
}