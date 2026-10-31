<?php

namespace App\Support;

/**
 * Rewrites Urdu-script property requirements into the Roman-Urdu / English
 * words the rules parser and location matcher already understand, e.g.
 * "مجھے ڈی ایچ اے لاہور میں دس مرلہ گھر چاہیے" → "mujhe dha lahore mein 10 marla ghar chahiye".
 *
 * Only known words are converted. Anything else is dropped from the parsed
 * copy (the original message is always kept), so an unknown Urdu word can
 * never be guessed into a city or area; the assistant asks instead.
 */
class UrduText
{
    /** Longest phrases first so "اسلام آباد" wins over "آباد". */
    protected const WORDS = [
        // Cities
        'اسلام آباد' => 'islamabad', 'فیصل آباد' => 'faisalabad', 'راولپنڈی' => 'rawalpindi', 'پنڈی' => 'pindi',
        'لاہور' => 'lahore', 'کراچی' => 'karachi', 'ملتان' => 'multan', 'کوئٹہ' => 'quetta', 'پشاور' => 'peshawar',
        // Common societies and areas
        'ڈی ایچ اے' => 'dha', 'ڈیفنس' => 'dha', 'بحریہ ٹاؤن' => 'bahria town', 'بحریہ' => 'bahria',
        'جوہر ٹاؤن' => 'johar town', 'ماڈل ٹاؤن' => 'model town', 'گارڈن ٹاؤن' => 'garden town', 'اقبال ٹاؤن' => 'iqbal town',
        'واپڈا ٹاؤن' => 'wapda town', 'گلبرگ' => 'gulberg', 'کلفٹن' => 'clifton', 'گلشن اقبال' => 'gulshan e iqbal',
        'حیات آباد' => 'hayatabad', 'کینٹ' => 'cantt', 'لیک سٹی' => 'lake city', 'ٹاؤن' => 'town', 'سوسائٹی' => 'society',
        'فیز' => 'phase', 'سیکٹر' => 'sector', 'بلاک' => 'block',
        // Property types
        'مکان' => 'house', 'گھر' => 'house', 'کوٹھی' => 'house', 'فلیٹ' => 'flat', 'اپارٹمنٹ' => 'apartment',
        'کمرشل پلاٹ' => 'commercial plot', 'پلاٹ' => 'plot', 'دکان' => 'shop', 'دفتر' => 'office', 'آفس' => 'office',
        'گودام' => 'warehouse', 'فارم ہاؤس' => 'farmhouse', 'پورشن' => 'portion', 'کمرشل' => 'commercial',
        // Units and money
        'مرلے' => 'marla', 'مرلہ' => 'marla', 'کنال' => 'kanal', 'گز' => 'gaz', 'مربع فٹ' => 'sqft', 'فٹ' => 'sqft',
        'کروڑ' => 'crore', 'لاکھ' => 'lakh', 'ہزار' => 'thousand', 'ملین' => 'million', 'روپے' => 'rs',
        // Rooms
        'بیڈروم' => 'bedrooms', 'بیڈ روم' => 'bedrooms', 'کمرے' => 'kamre', 'کمرہ' => 'kamra', 'باتھ روم' => 'bathrooms', 'باتھ' => 'bath',
        // Intent and connectors
        'کرائے' => 'kiraye', 'کرایہ' => 'kiraya', 'کرایے' => 'kiraye', 'خریدنا' => 'khareedna', 'خریدنے' => 'khareedna', 'خرید' => 'khareed',
        'بیچنا' => 'bechna', 'فروخت' => 'sale', 'تک' => 'tak', 'میں' => 'mein', 'چاہیے' => 'chahiye', 'چاہئے' => 'chahiye',
        'تقریباً' => 'around', 'تقریبا' => 'around', 'لگ بھگ' => 'around', 'آدھا' => 'aadha', 'آدھی' => 'aadha', 'ڈیڑھ' => 'derh', 'ڈھائی' => 'dhai',
        'کے ساتھ' => 'with', 'پارکنگ' => 'parking', 'فرنشڈ' => 'furnished', 'ڈیلر' => 'dealer', 'ایجنٹ' => 'agent', 'رابطہ' => 'contact',
        'شکریہ' => 'thanks', 'السلام علیکم' => 'salam', 'سلام' => 'salam',
        // Number words
        'ایک' => '1', 'دو' => '2', 'تین' => '3', 'چار' => '4', 'پانچ' => '5', 'چھ' => '6', 'سات' => '7', 'آٹھ' => '8', 'نو' => '9', 'دس' => '10',
        'بارہ' => '12', 'پندرہ' => '15', 'بیس' => '20', 'پچیس' => '25', 'پچاس' => '50',
    ];

    public static function hasUrdu(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    public static function toRoman(string $text): string
    {
        if (! self::hasUrdu($text)) {
            return $text;
        }

        // Urdu and Arabic-Indic digits → 0-9; Urdu comma/full stop → spaces.
        $text = strtr($text, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '،' => ' ', '۔' => ' ', '؟' => ' ']);

        $words = self::WORDS;
        uksort($words, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($words as $urdu => $roman) {
            // Whole words only (Urdu has no \b, so use letter look-arounds).
            $text = preg_replace('/(?<!\p{Arabic})'.preg_quote($urdu, '/').'(?!\p{Arabic})/u', ' '.$roman.' ', $text);
        }

        // Unknown Urdu words are dropped rather than guessed.
        $text = preg_replace('/\p{Arabic}+/u', ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
