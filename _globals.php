<?php if (!defined('ABSPATH')) exit;

/**
 * Recurring payments
 */
$GLOBALS['monthlySupport'] = ['stripe', 'paypal', 'banktransfer', 'gocardless', 'skrill'];

/**
 * Paypal
 */
$GLOBALS['paypalPayKeyEndpoint'] = array(
    'live'    => 'https://svcs.paypal.com/AdaptivePayments/Pay',
    'sandbox' => 'https://svcs.sandbox.paypal.com/AdaptivePayments/Pay',
);

$GLOBALS['paypalPaymentEndpoint'] = array(
    'live'    => 'https://www.paypal.com/webapps/adaptivepayment/flow/pay',
    'sandbox' => 'https://www.sandbox.paypal.com/webapps/adaptivepayment/flow/pay',
);

/**
 * Skrill
 */
$GLOBALS['SkrillApiEndpoint'] = 'https://pay.skrill.com';

/**
 * Coinbase
 */
$GLOBALS['CoinbaseApiEndpoint']    = 'https://api.commerce.coinbase.com';
$GLOBALS['CoinbaseChargeEndpoint'] = 'https://commerce.coinbase.com/charges';

/**
 * Supported bank account labels (listed here for automatic detection by Poedit)
 */
__("Beneficiary", "raise");
__("Beneficiary's address", "raise");
__("Account number", "raise");
__("IBAN", "raise");
__("BIC/SWIFT", "raise");
__("Sort code", "raise");
__("Routing number", "raise");
__("Bank", "raise");
__("Purpose", "raise");

/**
 * Payment provider key to payment provider label mapping
 */
$GLOBALS['pp_key2pp_label'] = array(
    'stripe'       => 'Stripe',
    'paypal'       => 'PayPal',
    'gocardless'   => 'GoCardless',
    'bitpay'       => 'BitPay',
    'coinbase'     => 'Coinbase',
    'skrill'       => 'Skrill',
    'banktransfer' => 'Bank Transfer',
);

/**
 * Currency/country mapping
 */
$GLOBALS['country2currency'] = array(
    "NZ" => "NZD",
    "CK" => "NZD",
    "NU" => "NZD",
    "PN" => "NZD",
    "TK" => "NZD",
    "AU" => "AUD",
    "CX" => "AUD",
    "CC" => "AUD",
    "HM" => "AUD",
    "KI" => "AUD",
    "NR" => "AUD",
    "NF" => "AUD",
    "TV" => "AUD",
    "AS" => "EUR",
    "AD" => "EUR",
    "AT" => "EUR",
    "BE" => "EUR",
    "FI" => "EUR",
    "FR" => "EUR",
    "GF" => "EUR",
    "TF" => "EUR",
    "DE" => "EUR",
    "GR" => "EUR",
    "GP" => "EUR",
    "IE" => "EUR",
    "IT" => "EUR",
    "LU" => "EUR",
    "MQ" => "EUR",
    "YT" => "EUR",
    "MC" => "EUR",
    "NL" => "EUR",
    "PT" => "EUR",
    "RE" => "EUR",
    "WS" => "EUR",
    "SM" => "EUR",
    "SI" => "EUR",
    "ES" => "EUR",
    "VA" => "EUR",
    "GS" => "GBP",
    "GB" => "GBP",
    "JE" => "GBP",
    "IO" => "USD",
    "GU" => "USD",
    "MH" => "USD",
    "FM" => "USD",
    "MP" => "USD",
    "PW" => "USD",
    "PR" => "USD",
    "TC" => "USD",
    "US" => "USD",
    "UM" => "USD",
    "VG" => "USD",
    "VI" => "USD",
    "HK" => "HKD",
    "CA" => "CAD",
    "JP" => "JPY",
    "AF" => "AFN",
    "AL" => "ALL",
    "DZ" => "DZD",
    "AI" => "XCD",
    "AG" => "XCD",
    "DM" => "XCD",
    "GD" => "XCD",
    "MS" => "XCD",
    "KN" => "XCD",
    "LC" => "XCD",
    "VC" => "XCD",
    "AR" => "ARS",
    "AM" => "AMD",
    "AW" => "ANG",
    "AN" => "ANG",
    "AZ" => "AZN",
    "BS" => "BSD",
    "BH" => "BHD",
    "BD" => "BDT",
    "BB" => "BBD",
    "BY" => "BYR",
    "BZ" => "BZD",
    "BJ" => "XOF",
    "BF" => "XOF",
    "GW" => "XOF",
    "CI" => "XOF",
    "ML" => "XOF",
    "NE" => "XOF",
    "SN" => "XOF",
    "TG" => "XOF",
    "BM" => "BMD",
    "BT" => "INR",
    "IN" => "INR",
    "BO" => "BOB",
    "BW" => "BWP",
    "BV" => "NOK",
    "NO" => "NOK",
    "SJ" => "NOK",
    "BR" => "BRL",
    "BN" => "BND",
    "BG" => "BGN",
    "BI" => "BIF",
    "KH" => "KHR",
    "CM" => "XAF",
    "CF" => "XAF",
    "TD" => "XAF",
    "CG" => "XAF",
    "GQ" => "XAF",
    "GA" => "XAF",
    "CV" => "CVE",
    "KY" => "KYD",
    "CL" => "CLP",
    "CN" => "CNY",
    "CO" => "COP",
    "KM" => "KMF",
    "CD" => "CDF",
    "CR" => "CRC",
    "HR" => "HRK",
    "CU" => "CUP",
    "CY" => "CYP",
    "CZ" => "CZK",
    "DK" => "DKK",
    "FO" => "DKK",
    "GL" => "DKK",
    "DJ" => "DJF",
    "DO" => "DOP",
    "TP" => "IDR",
    "ID" => "IDR",
    "EC" => "ECS",
    "EG" => "EGP",
    "SV" => "SVC",
    "ER" => "ETB",
    "ET" => "ETB",
    "EE" => "EEK",
    "FK" => "FKP",
    "FJ" => "FJD",
    "PF" => "XPF",
    "NC" => "XPF",
    "WF" => "XPF",
    "GM" => "GMD",
    "GE" => "GEL",
    "GI" => "GIP",
    "GT" => "GTQ",
    "GN" => "GNF",
    "GY" => "GYD",
    "HT" => "HTG",
    "HN" => "HNL",
    "HU" => "HUF",
    "IS" => "ISK",
    "IR" => "IRR",
    "IQ" => "IQD",
    "IL" => "ILS",
    "JM" => "JMD",
    "JO" => "JOD",
    "KZ" => "KZT",
    "KE" => "KES",
    "KP" => "KPW",
    "KR" => "KRW",
    "KW" => "KWD",
    "KG" => "KGS",
    "LA" => "LAK",
    "LV" => "LVL",
    "LB" => "LBP",
    "LS" => "LSL",
    "LR" => "LRD",
    "LY" => "LYD",
    "LI" => "CHF",
    "CH" => "CHF",
    "LT" => "LTL",
    "MO" => "MOP",
    "MK" => "MKD",
    "MG" => "MGA",
    "MW" => "MWK",
    "MY" => "MYR",
    "MV" => "MVR",
    "MT" => "EUR",
    "MR" => "MRO",
    "MU" => "MUR",
    "MX" => "MXN",
    "MD" => "MDL",
    "MN" => "MNT",
    "MA" => "MAD",
    "EH" => "MAD",
    "MZ" => "MZN",
    "MM" => "MMK",
    "NA" => "NAD",
    "NP" => "NPR",
    "NI" => "NIO",
    "NG" => "NGN",
    "OM" => "OMR",
    "PK" => "PKR",
    "PA" => "PAB",
    "PG" => "PGK",
    "PY" => "PYG",
    "PE" => "PEN",
    "PH" => "PHP",
    "PL" => "PLN",
    "QA" => "QAR",
    "RO" => "RON",
    "RU" => "RUB",
    "RW" => "RWF",
    "ST" => "STD",
    "SA" => "SAR",
    "SC" => "SCR",
    "SL" => "SLL",
    "SG" => "SGD",
    "SK" => "SKK",
    "SB" => "SBD",
    "SO" => "SOS",
    "ZA" => "ZAR",
    "LK" => "LKR",
    "SD" => "SDG",
    "SR" => "SRD",
    "SZ" => "SZL",
    "SE" => "SEK",
    "SY" => "SYP",
    "TW" => "TWD",
    "TJ" => "TJS",
    "TZ" => "TZS",
    "TH" => "THB",
    "TO" => "TOP",
    "TT" => "TTD",
    "TN" => "TND",
    "TR" => "TRY",
    "TM" => "TMT",
    "UG" => "UGX",
    "UA" => "UAH",
    "AE" => "AED",
    "UY" => "UYU",
    "UZ" => "UZS",
    "VU" => "VUV",
    "VE" => "VEF",
    "VN" => "VND",
    "YE" => "YER",
    "ZM" => "ZMK",
    "ZW" => "ZWD",
    "AX" => "EUR",
    "AO" => "AOA",
    "AQ" => "AQD",
    "BA" => "BAM",
    "GH" => "GHS",
    "GG" => "GGP",
    "IM" => "GBP",
    "MO" => "MOP",
    "ME" => "EUR",
    "PS" => "JOD",
    "BL" => "EUR",
    "SH" => "GBP",
    "MF" => "ANG",
    "PM" => "EUR",
    "RS" => "RSD",
    "USAF" => "USD",
);

$GLOBALS['currency2country'] = array(
    "AED" => array("AE"),
    "AFN" => array("AF"),
    "ALL" => array("AL"),
    "AMD" => array("AM"),
    "ANG" => array("AW","AN","MF"),
    "AOA" => array("AO"),
    "AQD" => array("AQ"),
    "ARS" => array("AR"),
    "AUD" => array("AU","CX","CC","HM","KI","NR","NF","TV"),
    "AZN" => array("AZ"),
    "BAM" => array("BA"),
    "BBD" => array("BB"),
    "BDT" => array("BD"),
    "BGN" => array("BG"),
    "BHD" => array("BH"),
    "BIF" => array("BI"),
    "BMD" => array("BM"),
    "BND" => array("BN"),
    "BOB" => array("BO"),
    "BRL" => array("BR"),
    "BSD" => array("BS"),
    "BWP" => array("BW"),
    "BYR" => array("BY"),
    "BZD" => array("BZ"),
    "CAD" => array("CA"),
    "CDF" => array("CD"),
    "CHF" => array("LI","CH"),
    "CLP" => array("CL"),
    "CNY" => array("CN"),
    "COP" => array("CO"),
    "CRC" => array("CR"),
    "CUP" => array("CU"),
    "CVE" => array("CV"),
    "CYP" => array("CY"),
    "CZK" => array("CZ"),
    "DJF" => array("DJ"),
    "DKK" => array("DK","FO","GL"),
    "DOP" => array("DO"),
    "DZD" => array("DZ"),
    "ECS" => array("EC"),
    "EEK" => array("EE"),
    "EGP" => array("EG"),
    "ETB" => array("ER","ET"),
    "EUR" => array("AS","AD","AT","BE","FI","FR","GF","TF","DE","GR","GP","IE","IT","LU","MQ","YT","MC","NL","PT","RE","WS","SM","SI","ES","VA","MT","AX","ME","BL","PM"),
    "FJD" => array("FJ"),
    "FKP" => array("FK"),
    "GBP" => array("GS","GB","JE","IM","SH"),
    "GEL" => array("GE"),
    "GGP" => array("GG"),
    "GHS" => array("GH"),
    "GIP" => array("GI"),
    "GMD" => array("GM"),
    "GNF" => array("GN"),
    "GTQ" => array("GT"),
    "GYD" => array("GY"),
    "HKD" => array("HK"),
    "HNL" => array("HN"),
    "HRK" => array("HR"),
    "HTG" => array("HT"),
    "HUF" => array("HU"),
    "IDR" => array("TP","ID"),
    "ILS" => array("IL"),
    "INR" => array("BT","IN"),
    "IQD" => array("IQ"),
    "IRR" => array("IR"),
    "ISK" => array("IS"),
    "JMD" => array("JM"),
    "JOD" => array("JO","PS"),
    "JPY" => array("JP"),
    "KES" => array("KE"),
    "KGS" => array("KG"),
    "KHR" => array("KH"),
    "KMF" => array("KM"),
    "KPW" => array("KP"),
    "KRW" => array("KR"),
    "KWD" => array("KW"),
    "KYD" => array("KY"),
    "KZT" => array("KZ"),
    "LAK" => array("LA"),
    "LBP" => array("LB"),
    "LKR" => array("LK"),
    "LRD" => array("LR"),
    "LSL" => array("LS"),
    "LTL" => array("LT"),
    "LVL" => array("LV"),
    "LYD" => array("LY"),
    "MAD" => array("MA","EH"),
    "MDL" => array("MD"),
    "MGA" => array("MG"),
    "MKD" => array("MK"),
    "MMK" => array("MM"),
    "MNT" => array("MN"),
    "MOP" => array("MO","MO"),
    "MRO" => array("MR"),
    "MUR" => array("MU"),
    "MVR" => array("MV"),
    "MWK" => array("MW"),
    "MXN" => array("MX"),
    "MYR" => array("MY"),
    "MZN" => array("MZ"),
    "NAD" => array("NA"),
    "NGN" => array("NG"),
    "NIO" => array("NI"),
    "NOK" => array("BV","NO","SJ"),
    "NPR" => array("NP"),
    "NZD" => array("NZ","CK","NU","PN","TK"),
    "OMR" => array("OM"),
    "PAB" => array("PA"),
    "PEN" => array("PE"),
    "PGK" => array("PG"),
    "PHP" => array("PH"),
    "PKR" => array("PK"),
    "PLN" => array("PL"),
    "PYG" => array("PY"),
    "QAR" => array("QA"),
    "RON" => array("RO"),
    "RSD" => array("RS"),
    "RUB" => array("RU"),
    "RWF" => array("RW"),
    "SAR" => array("SA"),
    "SBD" => array("SB"),
    "SCR" => array("SC"),
    "SDG" => array("SD"),
    "SEK" => array("SE"),
    "SGD" => array("SG"),
    "SKK" => array("SK"),
    "SLL" => array("SL"),
    "SOS" => array("SO"),
    "SRD" => array("SR"),
    "STD" => array("ST"),
    "SVC" => array("SV"),
    "SYP" => array("SY"),
    "SZL" => array("SZ"),
    "THB" => array("TH"),
    "TJS" => array("TJ"),
    "TMT" => array("TM"),
    "TND" => array("TN"),
    "TOP" => array("TO"),
    "TRY" => array("TR"),
    "TTD" => array("TT"),
    "TWD" => array("TW"),
    "TZS" => array("TZ"),
    "UAH" => array("UA"),
    "UGX" => array("UG"),
    "USD" => array("IO","GU","MH","FM","MP","PW","PR","TC","US","UM","VG","VI","USAF"),
    "UYU" => array("UY"),
    "UZS" => array("UZ"),
    "VEF" => array("VE"),
    "VND" => array("VN"),
    "VUV" => array("VU"),
    "XAF" => array("CM","CF","TD","CG","GQ","GA"),
    "XCD" => array("AI","AG","DM","GD","MS","KN","LC","VC"),
    "XOF" => array("BJ","BF","GW","CI","ML","NE","SN","TG"),
    "XPF" => array("PF","NC","WF"),
    "YER" => array("YE"),
    "ZAR" => array("ZA"),
    "ZMK" => array("ZM"),
    "ZWD" => array("ZW"),
);

$GLOBALS['code2country'] = array(
    "AF" => "Afghanistan",
    "AX" => "Åland Islands",
    "AL" => "Albania",
    "DZ" => "Algeria",
    "AS" => "American Samoa",
    "AD" => "Andorra",
    "AO" => "Angola",
    "AI" => "Anguilla",
    "AQ" => "Antarctica",
    "AG" => "Antigua and Barbuda",
    "AR" => "Argentina",
    "AM" => "Armenia",
    "AW" => "Aruba",
    "AU" => "Australia",
    "AT" => "Austria",
    "AZ" => "Azerbaijan",
    "BS" => "Bahamas",
    "BH" => "Bahrain",
    "BD" => "Bangladesh",
    "BB" => "Barbados",
    "BY" => "Belarus",
    "BE" => "Belgium",
    "BZ" => "Belize",
    "BJ" => "Benin",
    "BM" => "Bermuda",
    "BT" => "Bhutan",
    "BO" => "Bolivia, Plurinational State of",
    "BQ" => "Bonaire, Sint Eustatius and Saba",
    "BA" => "Bosnia and Herzegovina",
    "BW" => "Botswana",
    "BV" => "Bouvet Island",
    "BR" => "Brazil",
    "IO" => "British Indian Ocean Territory",
    "BN" => "Brunei Darussalam",
    "BG" => "Bulgaria",
    "BF" => "Burkina Faso",
    "BI" => "Burundi",
    "KH" => "Cambodia",
    "CM" => "Cameroon",
    "CA" => "Canada",
    "CV" => "Cape Verde",
    "KY" => "Cayman Islands",
    "CF" => "Central African Republic",
    "TD" => "Chad",
    "CL" => "Chile",
    "CN" => "China",
    "CX" => "Christmas Island",
    "CC" => "Cocos (Keeling) Islands",
    "CO" => "Colombia",
    "KM" => "Comoros",
    "CG" => "Congo, Republic of",
    "CD" => "Congo, Democratic Republic of the",
    "CK" => "Cook Islands",
    "CR" => "Costa Rica",
    "CI" => "Côte d'Ivoire",
    "HR" => "Croatia",
    "CU" => "Cuba",
    "CW" => "Curaçao",
    "CY" => "Cyprus",
    "CZ" => "Czech Republic",
    "DK" => "Denmark",
    "DJ" => "Djibouti",
    "DM" => "Dominica",
    "DO" => "Dominican Republic",
    "EC" => "Ecuador",
    "EG" => "Egypt",
    "SV" => "El Salvador",
    "GQ" => "Equatorial Guinea",
    "ER" => "Eritrea",
    "EE" => "Estonia",
    "ET" => "Ethiopia",
    "FK" => "Falkland Islands (Malvinas)",
    "FO" => "Faroe Islands",
    "FJ" => "Fiji",
    "FI" => "Finland",
    "FR" => "France",
    "GF" => "French Guiana",
    "PF" => "French Polynesia",
    "TF" => "French Southern Territories",
    "GA" => "Gabon",
    "GM" => "Gambia",
    "GE" => "Georgia",
    "DE" => "Germany",
    "GH" => "Ghana",
    "GI" => "Gibraltar",
    "GR" => "Greece",
    "GL" => "Greenland",
    "GD" => "Grenada",
    "GP" => "Guadeloupe",
    "GU" => "Guam",
    "GT" => "Guatemala",
    "GG" => "Guernsey",
    "GN" => "Guinea",
    "GW" => "Guinea-Bissau",
    "GY" => "Guyana",
    "HT" => "Haiti",
    "HM" => "Heard Island and McDonald Islands",
    "VA" => "Holy See (Vatican City State)",
    "HN" => "Honduras",
    "HK" => "Hong Kong",
    "HU" => "Hungary",
    "IS" => "Iceland",
    "IN" => "India",
    "ID" => "Indonesia",
    "IR" => "Iran, Islamic Republic of",
    "IQ" => "Iraq",
    "IE" => "Ireland",
    "IM" => "Isle of Man",
    "IL" => "Israel",
    "IT" => "Italy",
    "JM" => "Jamaica",
    "JP" => "Japan",
    "JE" => "Jersey",
    "JO" => "Jordan",
    "KZ" => "Kazakhstan",
    "KE" => "Kenya",
    "KI" => "Kiribati",
    "KP" => "Korea, Democratic People's Republic of",
    "KR" => "Korea, Republic of",
    "KW" => "Kuwait",
    "KG" => "Kyrgyzstan",
    "LA" => "Lao People's Democratic Republic",
    "LV" => "Latvia",
    "LB" => "Lebanon",
    "LS" => "Lesotho",
    "LR" => "Liberia",
    "LY" => "Libya",
    "LI" => "Liechtenstein",
    "LT" => "Lithuania",
    "LU" => "Luxembourg",
    "MO" => "Macao",
    "MK" => "Macedonia, Former Yugoslav Republic of",
    "MG" => "Madagascar",
    "MW" => "Malawi",
    "MY" => "Malaysia",
    "MV" => "Maldives",
    "ML" => "Mali",
    "MT" => "Malta",
    "MH" => "Marshall Islands",
    "MQ" => "Martinique",
    "MR" => "Mauritania",
    "MU" => "Mauritius",
    "YT" => "Mayotte",
    "MX" => "Mexico",
    "FM" => "Micronesia, Federated States of",
    "MD" => "Moldova, Republic of",
    "MC" => "Monaco",
    "MN" => "Mongolia",
    "ME" => "Montenegro",
    "MS" => "Montserrat",
    "MA" => "Morocco",
    "MZ" => "Mozambique",
    "MM" => "Myanmar",
    "NA" => "Namibia",
    "NR" => "Nauru",
    "NP" => "Nepal",
    "NL" => "Netherlands",
    "NC" => "New Caledonia",
    "NZ" => "New Zealand",
    "NI" => "Nicaragua",
    "NE" => "Niger",
    "NG" => "Nigeria",
    "NU" => "Niue",
    "NF" => "Norfolk Island",
    "MP" => "Northern Mariana Islands",
    "NO" => "Norway",
    "OM" => "Oman",
    "PK" => "Pakistan",
    "PW" => "Palau",
    "PS" => "Palestinian Territory, Occupied",
    "PA" => "Panama",
    "PG" => "Papua New Guinea",
    "PY" => "Paraguay",
    "PE" => "Peru",
    "PH" => "Philippines",
    "PN" => "Pitcairn",
    "PL" => "Poland",
    "PT" => "Portugal",
    "PR" => "Puerto Rico",
    "QA" => "Qatar",
    "RE" => "Réunion",
    "RO" => "Romania",
    "RU" => "Russian Federation",
    "RW" => "Rwanda",
    "SH" => "Saint Helena, Ascension and Tristan da Cunha",
    "KN" => "Saint Kitts and Nevis",
    "LC" => "Saint Lucia",
    "PM" => "Saint Pierre and Miquelon",
    "VC" => "Saint Vincent and the Grenadines",
    "WS" => "Samoa",
    "SM" => "San Marino",
    "ST" => "Sao Tome and Principe",
    "SA" => "Saudi Arabia",
    "SN" => "Senegal",
    "RS" => "Serbia",
    "SC" => "Seychelles",
    "SL" => "Sierra Leone",
    "SG" => "Singapore",
    "SK" => "Slovakia",
    "SI" => "Slovenia",
    "SB" => "Solomon Islands",
    "SO" => "Somalia",
    "ZA" => "South Africa",
    "GS" => "South Georgia and the South Sandwich Islands",
    "SS" => "South Sudan",
    "ES" => "Spain",
    "LK" => "Sri Lanka",
    "SD" => "Sudan",
    "SR" => "Suriname",
    "SJ" => "Svalbard and Jan Mayen",
    "SZ" => "Swaziland",
    "SE" => "Sweden",
    "CH" => "Switzerland",
    "SY" => "Syrian Arab Republic",
    "TW" => "Taiwan, Province of China",
    "TJ" => "Tajikistan",
    "TZ" => "Tanzania, United Republic of",
    "TH" => "Thailand",
    "TL" => "Timor-Leste",
    "TG" => "Togo",
    "TK" => "Tokelau",
    "TO" => "Tonga",
    "TT" => "Trinidad and Tobago",
    "TN" => "Tunisia",
    "TR" => "Turkey",
    "TM" => "Turkmenistan",
    "TC" => "Turks and Caicos Islands",
    "TV" => "Tuvalu",
    "UG" => "Uganda",
    "UA" => "Ukraine",
    "AE" => "United Arab Emirates",
    "GB" => "United Kingdom",
    "US" => "United States",
    "UM" => "United States Minor Outlying Islands",
    "UY" => "Uruguay",
    "UZ" => "Uzbekistan",
    "VU" => "Vanuatu",
    "VE" => "Venezuela, Bolivarian Republic of",
    "VN" => "Viet Nam",
    "VG" => "Virgin Islands, British",
    "VI" => "Virgin Islands, U.S.",
    "WF" => "Wallis and Futuna",
    "EH" => "Western Sahara",
    "YE" => "Yemen",
    "ZM" => "Zambia",
    "ZW" => "Zimbabwe",
);
