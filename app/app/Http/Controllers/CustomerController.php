<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CustomerController extends Controller
{
    // list all customers
    public function index()
    {
        return "index is working";
    }

    // return customer by id
    public function show()
    {
        return "show is working";
    }

    // create customer
    public function store()
    {
        return "store is working";
    }

    // update customer by id
    public function update()
    {
        return "update is working";
    }

    // delete customer by id
    public function destroy()
    {
        return "destroy is working";
    }
}
