@props(['amount' => null])

<span class="currency-value">
    @if($amount !== null)
        {{ number_format((float) $amount, 2) }}
    @endif
    <img
        class="currency-mark"
        src="https://www.sama.gov.sa/ar-sa/Currency/Documents/Saudi_Riyal_Symbol-1.png"
        alt="رمز الريال السعودي"
        title="الريال السعودي"
    >
</span>
