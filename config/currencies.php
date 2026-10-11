<?php

/*
|--------------------------------------------------------------------------
| Currencies the store can choose from (Settings → Store → Currency)
|--------------------------------------------------------------------------
| code => [name, region, [symbol options]]  – the first symbol is the default.
| The store can also type its own symbol (e.g. "N" or "NGN") in settings.
| Letter symbols of 2+ characters get a space before the amount automatically
| (NGN 1,250.00, KSh 500.00); others don't (₦1,250.00, N1,250.00).
*/

return [
    // ---------------- Africa
    'NGN' => ['Nigerian Naira', 'Africa', ['₦', 'N', 'NGN']],
    'GHS' => ['Ghanaian Cedi', 'Africa', ['GH₵', '₵', 'GHS']],
    'KES' => ['Kenyan Shilling', 'Africa', ['KSh', 'Ksh', 'KES']],
    'ZAR' => ['South African Rand', 'Africa', ['R', 'ZAR']],
    'EGP' => ['Egyptian Pound', 'Africa', ['E£', 'EGP', 'ج.م']],
    'XOF' => ['West African CFA Franc', 'Africa', ['CFA', 'F CFA', 'XOF']],
    'XAF' => ['Central African CFA Franc', 'Africa', ['FCFA', 'XAF']],
    'UGX' => ['Ugandan Shilling', 'Africa', ['USh', 'UGX']],
    'TZS' => ['Tanzanian Shilling', 'Africa', ['TSh', 'TZS']],
    'RWF' => ['Rwandan Franc', 'Africa', ['FRw', 'RF', 'RWF']],
    'ETB' => ['Ethiopian Birr', 'Africa', ['Br', 'ETB']],
    'MAD' => ['Moroccan Dirham', 'Africa', ['DH', 'MAD']],
    'DZD' => ['Algerian Dinar', 'Africa', ['DA', 'DZD']],
    'TND' => ['Tunisian Dinar', 'Africa', ['DT', 'TND']],
    'LYD' => ['Libyan Dinar', 'Africa', ['LD', 'LYD']],
    'ZMW' => ['Zambian Kwacha', 'Africa', ['ZK', 'ZMW']],
    'MWK' => ['Malawian Kwacha', 'Africa', ['MK', 'MWK']],
    'BWP' => ['Botswana Pula', 'Africa', ['P', 'BWP']],
    'NAD' => ['Namibian Dollar', 'Africa', ['N$', 'NAD']],
    'MZN' => ['Mozambican Metical', 'Africa', ['MT', 'MZN']],
    'AOA' => ['Angolan Kwanza', 'Africa', ['Kz', 'AOA']],
    'CDF' => ['Congolese Franc', 'Africa', ['FC', 'CDF']],
    'GNF' => ['Guinean Franc', 'Africa', ['FG', 'GNF']],
    'SLE' => ['Sierra Leonean Leone', 'Africa', ['Le', 'SLE']],
    'LRD' => ['Liberian Dollar', 'Africa', ['L$', 'LRD']],
    'GMD' => ['Gambian Dalasi', 'Africa', ['D', 'GMD']],
    'MUR' => ['Mauritian Rupee', 'Africa', ['₨', 'Rs', 'MUR']],

    // ---------------- Americas
    'USD' => ['US Dollar', 'Americas', ['$', 'US$', 'USD']],
    'CAD' => ['Canadian Dollar', 'Americas', ['$', 'C$', 'CAD']],
    'MXN' => ['Mexican Peso', 'Americas', ['$', 'MX$', 'MXN']],
    'BRL' => ['Brazilian Real', 'Americas', ['R$', 'BRL']],
    'ARS' => ['Argentine Peso', 'Americas', ['$', 'ARS']],
    'COP' => ['Colombian Peso', 'Americas', ['$', 'COL$', 'COP']],
    'CLP' => ['Chilean Peso', 'Americas', ['$', 'CLP']],
    'PEN' => ['Peruvian Sol', 'Americas', ['S/', 'PEN']],
    'JMD' => ['Jamaican Dollar', 'Americas', ['J$', '$', 'JMD']],
    'TTD' => ['Trinidad & Tobago Dollar', 'Americas', ['TT$', 'TTD']],

    // ---------------- Europe
    'EUR' => ['Euro', 'Europe', ['€', 'EUR']],
    'GBP' => ['British Pound', 'Europe', ['£', 'GBP']],
    'CHF' => ['Swiss Franc', 'Europe', ['CHF', 'Fr']],
    'SEK' => ['Swedish Krona', 'Europe', ['kr', 'SEK']],
    'NOK' => ['Norwegian Krone', 'Europe', ['kr', 'NOK']],
    'DKK' => ['Danish Krone', 'Europe', ['kr', 'DKK']],
    'ISK' => ['Icelandic Króna', 'Europe', ['kr', 'ISK']],
    'PLN' => ['Polish Złoty', 'Europe', ['zł', 'PLN']],
    'CZK' => ['Czech Koruna', 'Europe', ['Kč', 'CZK']],
    'HUF' => ['Hungarian Forint', 'Europe', ['Ft', 'HUF']],
    'RON' => ['Romanian Leu', 'Europe', ['lei', 'RON']],
    'BGN' => ['Bulgarian Lev', 'Europe', ['лв', 'BGN']],
    'RSD' => ['Serbian Dinar', 'Europe', ['дин', 'RSD']],
    'UAH' => ['Ukrainian Hryvnia', 'Europe', ['₴', 'UAH']],
    'RUB' => ['Russian Ruble', 'Europe', ['₽', 'RUB']],
    'TRY' => ['Turkish Lira', 'Europe', ['₺', 'TL', 'TRY']],

    // ---------------- Middle East
    'AED' => ['UAE Dirham', 'Middle East', ['AED', 'د.إ', 'Dh']],
    'SAR' => ['Saudi Riyal', 'Middle East', ['SR', '﷼', 'SAR']],
    'QAR' => ['Qatari Riyal', 'Middle East', ['QR', '﷼', 'QAR']],
    'KWD' => ['Kuwaiti Dinar', 'Middle East', ['KD', 'د.ك', 'KWD']],
    'BHD' => ['Bahraini Dinar', 'Middle East', ['BD', 'BHD']],
    'OMR' => ['Omani Rial', 'Middle East', ['OMR', '﷼']],
    'JOD' => ['Jordanian Dinar', 'Middle East', ['JD', 'JOD']],
    'ILS' => ['Israeli Shekel', 'Middle East', ['₪', 'ILS']],

    // ---------------- Asia & Pacific
    'INR' => ['Indian Rupee', 'Asia & Pacific', ['₹', 'Rs', 'INR']],
    'PKR' => ['Pakistani Rupee', 'Asia & Pacific', ['Rs', '₨', 'PKR']],
    'BDT' => ['Bangladeshi Taka', 'Asia & Pacific', ['৳', 'Tk', 'BDT']],
    'LKR' => ['Sri Lankan Rupee', 'Asia & Pacific', ['Rs', 'LKR']],
    'NPR' => ['Nepalese Rupee', 'Asia & Pacific', ['Rs', 'रू', 'NPR']],
    'CNY' => ['Chinese Yuan', 'Asia & Pacific', ['¥', 'CN¥', 'CNY']],
    'JPY' => ['Japanese Yen', 'Asia & Pacific', ['¥', 'JPY']],
    'KRW' => ['South Korean Won', 'Asia & Pacific', ['₩', 'KRW']],
    'IDR' => ['Indonesian Rupiah', 'Asia & Pacific', ['Rp', 'IDR']],
    'MYR' => ['Malaysian Ringgit', 'Asia & Pacific', ['RM', 'MYR']],
    'PHP' => ['Philippine Peso', 'Asia & Pacific', ['₱', 'PHP']],
    'THB' => ['Thai Baht', 'Asia & Pacific', ['฿', 'THB']],
    'VND' => ['Vietnamese Dong', 'Asia & Pacific', ['₫', 'VND']],
    'SGD' => ['Singapore Dollar', 'Asia & Pacific', ['S$', '$', 'SGD']],
    'HKD' => ['Hong Kong Dollar', 'Asia & Pacific', ['HK$', '$', 'HKD']],
    'TWD' => ['New Taiwan Dollar', 'Asia & Pacific', ['NT$', 'TWD']],
    'AUD' => ['Australian Dollar', 'Asia & Pacific', ['$', 'A$', 'AUD']],
    'NZD' => ['New Zealand Dollar', 'Asia & Pacific', ['$', 'NZ$', 'NZD']],
];
