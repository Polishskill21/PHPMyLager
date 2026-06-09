import './bootstrap';

const DATE_PICKER_MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];
const DATE_PICKER_WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const DATE_INPUT_SELECTOR = '.date-field';
let openDatePickerInput = null;

function openDatePicker(input) {
    if (!input) return;

    const wrap = input.closest('.date-input-wrap');
    if (!wrap) return;

    closeDatePickers(input);

    let picker = wrap.querySelector('.date-picker');
    if (!picker) {
        picker = document.createElement('div');
        picker.className = 'date-picker';
        picker.hidden = true;
        picker.setAttribute('role', 'dialog');
        picker.setAttribute('aria-label', 'Choose date');
        picker.addEventListener('click', (event) => event.stopPropagation());
        wrap.appendChild(picker);
    }

    const selectedDate = parseDateValue(input.value);
    const baseDate = selectedDate || new Date();
    if (input._datePickerValue !== input.value || !input._datePickerMonth) {
        input._datePickerMonth = new Date(baseDate.getFullYear(), baseDate.getMonth(), 1);
        input._datePickerValue = input.value;
    }

    renderDatePicker(input, picker);
    picker.hidden = false;
    openDatePickerInput = input;
}

function closeDatePickers(exceptInput = null) {
    document.querySelectorAll('.date-picker').forEach((picker) => {
        if (exceptInput && picker.parentElement?.contains(exceptInput)) return;
        picker.hidden = true;
    });

    if (!exceptInput || openDatePickerInput !== exceptInput) {
        openDatePickerInput = null;
    }
}

function renderDatePicker(input, picker) {
    const monthDate = input._datePickerMonth || new Date();
    const year = monthDate.getFullYear();
    const month = monthDate.getMonth();
    const selected = parseDateValue(input.value);
    const today = new Date();
    const firstDay = new Date(year, month, 1);
    const firstWeekday = (firstDay.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const cellCount = Math.ceil((firstWeekday + daysInMonth) / 7) * 7;
    const cells = [];

    for (let i = 0; i < cellCount; i += 1) {
        const day = i - firstWeekday + 1;

        if (day < 1 || day > daysInMonth) {
            cells.push('<span class="date-picker-empty" aria-hidden="true"></span>');
            continue;
        }

        const date = new Date(year, month, day);
        const value = formatDateValue(date);
        const classes = [
            'date-picker-day',
            sameDate(date, today) ? 'is-today' : '',
            selected && sameDate(date, selected) ? 'is-selected' : '',
        ].filter(Boolean).join(' ');

        cells.push(`<button type="button" class="${classes}" data-date="${value}">${day}</button>`);
    }

    picker.innerHTML = `
        <div class="date-picker-head">
            <button type="button" class="date-picker-nav" data-month-step="-1" aria-label="Previous month">‹</button>
            <div class="date-picker-title">${DATE_PICKER_MONTHS[month]} ${year}</div>
            <button type="button" class="date-picker-nav" data-month-step="1" aria-label="Next month">›</button>
        </div>
        <div class="date-picker-grid">
            ${DATE_PICKER_WEEKDAYS.map((day) => `<div class="date-picker-weekday">${day}</div>`).join('')}
            ${cells.join('')}
        </div>
    `;

    picker.querySelectorAll('[data-month-step]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const step = Number(button.dataset.monthStep);
            input._datePickerMonth = new Date(year, month + step, 1);
            renderDatePicker(input, picker);
        });
    });

    picker.querySelectorAll('[data-date]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            input.value = button.dataset.date;
            input._datePickerValue = input.value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            closeDatePickers();
        });
    });
}

function parseDateValue(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;

    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDateValue(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function sameDate(a, b) {
    return a.getFullYear() === b.getFullYear()
        && a.getMonth() === b.getMonth()
        && a.getDate() === b.getDate();
}

window.AppDatePicker = {
    open: openDatePicker,
    closeAll: closeDatePickers,
    hasOpen: () => Boolean(openDatePickerInput),
};

document.addEventListener('click', (event) => {
    const input = closestTarget(event.target, DATE_INPUT_SELECTOR);
    if (input) {
        openDatePicker(input);
        return;
    }

    if (closestTarget(event.target, '.date-picker')) return;
    if (!closestTarget(event.target, '.date-input-wrap')) closeDatePickers();
});

document.addEventListener('focusin', (event) => {
    const input = closestTarget(event.target, DATE_INPUT_SELECTOR);
    if (input) openDatePicker(input);
});

document.addEventListener('keydown', (event) => {
    const input = closestTarget(event.target, DATE_INPUT_SELECTOR);
    if (input && (event.key === 'Enter' || event.key === ' ')) {
        event.preventDefault();
        openDatePicker(input);
        return;
    }

    if (event.key === 'Escape' && openDatePickerInput) {
        closeDatePickers();
        event.stopPropagation();
    }
});

document.addEventListener('click', (event) => {
    const button = closestTarget(event.target, '[data-number-step]');
    if (!button) return;

    const wrap = button.closest('.number-input-wrap');
    const input = wrap?.querySelector('input[type="number"]');
    if (!input || input.disabled || input.readOnly) return;

    const direction = button.dataset.numberStep === 'up' ? 1 : -1;
    const step = Number(input.step || 1) || 1;
    const min = input.min === '' ? -Infinity : Number(input.min);
    const max = input.max === '' ? Infinity : Number(input.max);
    const precision = decimalPlaces(input.step);
    const current = Number(input.value);
    let next = Number.isFinite(current)
        ? current + direction * step
        : (Number.isFinite(min) ? min : step);

    if (Number.isFinite(min)) next = Math.max(min, next);
    if (Number.isFinite(max)) next = Math.min(max, next);

    input.value = precision > 0 ? next.toFixed(precision) : String(Math.round(next));
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
});

function decimalPlaces(value) {
    const text = String(value || '');
    const [, decimals = ''] = text.split('.');
    return decimals.length;
}

function closestTarget(target, selector) {
    return target instanceof Element ? target.closest(selector) : null;
}
