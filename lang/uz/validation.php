<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute maydoni qabul qilinishi shart.',
    'accepted_if' => ':other :value bo‘lganda :attribute maydoni qabul qilinishi shart.',
    'active_url' => ':attribute maydoni to‘g‘ri URL bo‘lishi shart.',
    'after' => ':attribute maydoni :date dan keyingi sana bo‘lishi shart.',
    'after_or_equal' => ':attribute maydoni :date ga teng yoki undan keyingi sana bo‘lishi shart.',
    'alpha' => ':attribute maydoni faqat harflardan iborat bo‘lishi shart.',
    'alpha_dash' => ':attribute maydoni faqat harflar, raqamlar, chiziqcha va pastki chiziqlardan iborat bo‘lishi shart.',
    'alpha_num' => ':attribute maydoni faqat harflar va raqamlardan iborat bo‘lishi shart.',
    'any_of' => ':attribute maydoni noto‘g‘ri.',
    'array' => ':attribute maydoni massiv bo‘lishi shart.',
    'ascii' => ':attribute maydoni faqat bir baytli harf-raqamli belgilar va simvollardan iborat bo‘lishi shart.',
    'before' => ':attribute maydoni :date dan oldingi sana bo‘lishi shart.',
    'before_or_equal' => ':attribute maydoni :date ga teng yoki undan oldingi sana bo‘lishi shart.',
    'between' => [
        'array' => ':attribute maydoni :min dan :max gacha element bo‘lishi shart.',
        'file' => ':attribute fayli hajmi :min dan :max kilobaytgacha bo‘lishi shart.',
        'numeric' => ':attribute maydoni :min dan :max gacha bo‘lishi shart.',
        'string' => ':attribute maydoni :min dan :max gacha belgidan iborat bo‘lishi shart.',
    ],
    'boolean' => ':attribute maydoni true yoki false bo‘lishi shart.',
    'can' => ':attribute maydoni ruxsat etilmagan qiymatni o‘z ichiga oladi.',
    'confirmed' => ':attribute maydoni tasdig‘i mos kelmadi.',
    'contains' => ':attribute maydonida talab qilingan qiymat yetishmayapti.',
    'current_password' => 'Parol noto‘g‘ri.',
    'date' => ':attribute maydoni to‘g‘ri sana bo‘lishi shart.',
    'date_equals' => ':attribute maydoni :date ga teng sana bo‘lishi shart.',
    'date_format' => ':attribute maydoni :format formatiga mos bo‘lishi shart.',
    'decimal' => ':attribute maydoni :decimal ta o‘nlik belgiga ega bo‘lishi shart.',
    'declined' => ':attribute maydoni rad etilishi shart.',
    'declined_if' => ':other :value bo‘lganda :attribute maydoni rad etilishi shart.',
    'different' => ':attribute va :other maydonlari har xil bo‘lishi shart.',
    'digits' => ':attribute maydoni :digits ta raqamdan iborat bo‘lishi shart.',
    'digits_between' => ':attribute maydoni :min dan :max gacha raqamdan iborat bo‘lishi shart.',
    'dimensions' => ':attribute maydonida rasm o‘lchamlari noto‘g‘ri.',
    'distinct' => ':attribute maydonida takrorlanuvchi qiymat mavjud.',
    'doesnt_contain' => ':attribute maydoni quyidagilarning hech birini o‘z ichiga olmasligi shart: :values.',
    'doesnt_end_with' => ':attribute maydoni quyidagilarning biri bilan tugamasligi shart: :values.',
    'doesnt_start_with' => ':attribute maydoni quyidagilarning biri bilan boshlanmasligi shart: :values.',
    'email' => ':attribute maydoni to‘g‘ri elektron pochta manzili bo‘lishi shart.',
    'encoding' => ':attribute maydoni :encoding kodlashda bo‘lishi shart.',
    'ends_with' => ':attribute maydoni quyidagilarning biri bilan tugashi shart: :values.',
    'enum' => 'Tanlangan :attribute noto‘g‘ri.',
    'exists' => 'Tanlangan :attribute noto‘g‘ri.',
    'extensions' => ':attribute maydoni quyidagi kengaytmalardan biriga ega bo‘lishi shart: :values.',
    'file' => ':attribute maydoni fayl bo‘lishi shart.',
    'filled' => ':attribute maydoni qiymatga ega bo‘lishi shart.',
    'gt' => [
        'array' => ':attribute maydoni :value tadan ortiq element bo‘lishi shart.',
        'file' => ':attribute fayli hajmi :value kilobaytdan katta bo‘lishi shart.',
        'numeric' => ':attribute maydoni :value dan katta bo‘lishi shart.',
        'string' => ':attribute maydoni :value belgidan ortiq bo‘lishi shart.',
    ],
    'gte' => [
        'array' => ':attribute maydoni :value ta yoki undan ko‘p element bo‘lishi shart.',
        'file' => ':attribute fayli hajmi :value kilobaytdan katta yoki teng bo‘lishi shart.',
        'numeric' => ':attribute maydoni :value dan katta yoki teng bo‘lishi shart.',
        'string' => ':attribute maydoni :value ta yoki undan ko‘p belgidan iborat bo‘lishi shart.',
    ],
    'hex_color' => ':attribute maydoni to‘g‘ri o‘n oltilik rang kodi bo‘lishi shart.',
    'image' => ':attribute maydoni rasm bo‘lishi shart.',
    'in' => 'Tanlangan :attribute noto‘g‘ri.',
    'in_array' => ':attribute maydoni :other ichida mavjud bo‘lishi shart.',
    'in_array_keys' => ':attribute maydoni quyidagi kalitlardan kamida bittasini o‘z ichiga olishi shart: :values.',
    'integer' => ':attribute maydoni butun son bo‘lishi shart.',
    'ip' => ':attribute maydoni to‘g‘ri IP manzil bo‘lishi shart.',
    'ipv4' => ':attribute maydoni to‘g‘ri IPv4 manzil bo‘lishi shart.',
    'ipv6' => ':attribute maydoni to‘g‘ri IPv6 manzil bo‘lishi shart.',
    'json' => ':attribute maydoni to‘g‘ri JSON satri bo‘lishi shart.',
    'list' => ':attribute maydoni ro‘yxat bo‘lishi shart.',
    'lowercase' => ':attribute maydoni kichik harflarda bo‘lishi shart.',
    'lt' => [
        'array' => ':attribute maydoni :value tadan kam element bo‘lishi shart.',
        'file' => ':attribute fayli hajmi :value kilobaytdan kichik bo‘lishi shart.',
        'numeric' => ':attribute maydoni :value dan kichik bo‘lishi shart.',
        'string' => ':attribute maydoni :value belgidan kam bo‘lishi shart.',
    ],
    'lte' => [
        'array' => ':attribute maydoni :value tadan ortiq element bo‘lmasligi shart.',
        'file' => ':attribute fayli hajmi :value kilobaytdan kichik yoki teng bo‘lishi shart.',
        'numeric' => ':attribute maydoni :value dan kichik yoki teng bo‘lishi shart.',
        'string' => ':attribute maydoni :value belgidan ko‘p bo‘lmasligi shart.',
    ],
    'mac_address' => ':attribute maydoni to‘g‘ri MAC manzil bo‘lishi shart.',
    'max' => [
        'array' => ':attribute maydoni :max tadan ortiq element bo‘lmasligi shart.',
        'file' => ':attribute fayli hajmi :max kilobaytdan oshmasligi shart.',
        'numeric' => ':attribute maydoni :max dan oshmasligi shart.',
        'string' => ':attribute maydoni :max belgidan oshmasligi shart.',
    ],
    'max_digits' => ':attribute maydoni :max tadan ortiq raqamga ega bo‘lmasligi shart.',
    'mimes' => ':attribute maydoni quyidagi turdagi fayl bo‘lishi shart: :values.',
    'mimetypes' => ':attribute maydoni quyidagi turdagi fayl bo‘lishi shart: :values.',
    'min' => [
        'array' => ':attribute maydoni kamida :min ta element bo‘lishi shart.',
        'file' => ':attribute fayli hajmi kamida :min kilobayt bo‘lishi shart.',
        'numeric' => ':attribute maydoni kamida :min bo‘lishi shart.',
        'string' => ':attribute maydoni kamida :min belgidan iborat bo‘lishi shart.',
    ],
    'min_digits' => ':attribute maydoni kamida :min ta raqamga ega bo‘lishi shart.',
    'missing' => ':attribute maydoni mavjud bo‘lmasligi shart.',
    'missing_if' => ':other :value bo‘lganda :attribute maydoni mavjud bo‘lmasligi shart.',
    'missing_unless' => ':other :value bo‘lmaganda :attribute maydoni mavjud bo‘lmasligi shart.',
    'missing_with' => ':values mavjud bo‘lganda :attribute maydoni mavjud bo‘lmasligi shart.',
    'missing_with_all' => ':values mavjud bo‘lganda :attribute maydoni mavjud bo‘lmasligi shart.',
    'multiple_of' => ':attribute maydoni :value ga karrali bo‘lishi shart.',
    'not_in' => 'Tanlangan :attribute noto‘g‘ri.',
    'not_regex' => ':attribute maydoni formati noto‘g‘ri.',
    'numeric' => ':attribute maydoni son bo‘lishi shart.',
    'password' => [
        'letters' => ':attribute maydoni kamida bitta harf bilan bo‘lishi shart.',
        'mixed' => ':attribute maydoni kamida bitta bosh va bitta kichik harfdan iborat bo‘lishi shart.',
        'numbers' => ':attribute maydoni kamida bitta raqamdan iborat bo‘lishi shart.',
        'symbols' => ':attribute maydoni kamida bitta simvoldan iborat bo‘lishi shart.',
        'uncompromised' => 'Kiritilgan :attribute ma’lumotlar sizib chiqishida aniqlandi. Iltimos, boshqa :attribute tanlang.',
    ],
    'present' => ':attribute maydoni mavjud bo‘lishi shart.',
    'present_if' => ':other :value bo‘lganda :attribute maydoni mavjud bo‘lishi shart.',
    'present_unless' => ':other :value bo‘lmaganda :attribute maydoni mavjud bo‘lishi shart.',
    'present_with' => ':values mavjud bo‘lganda :attribute maydoni mavjud bo‘lishi shart.',
    'present_with_all' => ':values mavjud bo‘lganda :attribute maydoni mavjud bo‘lishi shart.',
    'prohibited' => ':attribute maydoni taqiqlangan.',
    'prohibited_if' => ':other :value bo‘lganda :attribute maydoni taqiqlanadi.',
    'prohibited_if_accepted' => ':other qabul qilinganda :attribute maydoni taqiqlanadi.',
    'prohibited_if_declined' => ':other rad etilganda :attribute maydoni taqiqlanadi.',
    'prohibited_unless' => ':other :values ichida bo‘lmasa :attribute maydoni taqiqlanadi.',
    'prohibits' => ':attribute maydoni :other ning mavjud bo‘lishini taqiqlaydi.',
    'regex' => ':attribute maydoni formati noto‘g‘ri.',
    'required' => ':attribute maydoni to‘ldirilishi shart.',
    'required_array_keys' => ':attribute maydoni quyidagilar uchun yozuvlarni o‘z ichiga olishi shart: :values.',
    'required_if' => ':other :value bo‘lganda :attribute maydoni to‘ldirilishi shart.',
    'required_if_accepted' => ':other qabul qilinganda :attribute maydoni to‘ldirilishi shart.',
    'required_if_declined' => ':other rad etilganda :attribute maydoni to‘ldirilishi shart.',
    'required_unless' => ':other :values ichida bo‘lmasa :attribute maydoni to‘ldirilishi shart.',
    'required_with' => ':values mavjud bo‘lganda :attribute maydoni to‘ldirilishi shart.',
    'required_with_all' => ':values mavjud bo‘lganda :attribute maydoni to‘ldirilishi shart.',
    'required_without' => ':values mavjud bo‘lmaganda :attribute maydoni to‘ldirilishi shart.',
    'required_without_all' => ':values ning hech biri mavjud bo‘lmaganda :attribute maydoni to‘ldirilishi shart.',
    'same' => ':attribute maydoni :other ga mos kelishi shart.',
    'size' => [
        'array' => ':attribute maydoni :size ta element bo‘lishi shart.',
        'file' => ':attribute fayli hajmi :size kilobayt bo‘lishi shart.',
        'numeric' => ':attribute maydoni :size bo‘lishi shart.',
        'string' => ':attribute maydoni :size belgidan iborat bo‘lishi shart.',
    ],
    'starts_with' => ':attribute maydoni quyidagilarning biri bilan boshlanishi shart: :values.',
    'string' => ':attribute maydoni satr bo‘lishi shart.',
    'timezone' => ':attribute maydoni to‘g‘ri vaqt mintaqasi bo‘lishi shart.',
    'unique' => ':attribute maydoni qiymati allaqachon band.',
    'uploaded' => ':attribute fayli yuklanmadi.',
    'uppercase' => ':attribute maydoni bosh harflarda bo‘lishi shart.',
    'url' => ':attribute maydoni to‘g‘ri URL bo‘lishi shart.',
    'ulid' => ':attribute maydoni to‘g‘ri ULID bo‘lishi shart.',
    'uuid' => ':attribute maydoni to‘g‘ri UUID bo‘lishi shart.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
