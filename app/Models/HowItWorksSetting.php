<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'page_title_en',
    'page_title_sw',
    'intro_en',
    'intro_sw',
    'example_heading_en',
    'example_heading_sw',
    'example_intro_en',
    'example_intro_sw',
    'example_note_en',
    'example_note_sw',
    'what_you_pay_title_en',
    'what_you_pay_title_sw',
    'what_you_pay_body_en',
    'what_you_pay_body_sw',
    'transparency_title_en',
    'transparency_title_sw',
    'transparency_body_en',
    'transparency_body_sw',
])]
class HowItWorksSetting extends Model
{
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'page_title_en' => 'How UniqBid works',
            'page_title_sw' => 'UniqBid Inavyofanya Kazi',
            'intro_en' => 'The lowest unique bid secures the item. Unique means no one else placed exactly that amount. Here\'s the whole idea in one page.',
            'intro_sw' => 'Zabuni ya chini kabisa isiyo na mfano ndiyo inayopata bidhaa. Hii ndio dhana yote katika ukurasa mmoja.',
            'example_heading_en' => 'A worked example',
            'example_heading_sw' => 'Mfano wa kazi',
            'example_intro_en' => 'Five people bid on the same item. Who secures the buy?',
            'example_intro_sw' => 'Watu watano wanaweka zabuni kwenye bidhaa moja. Nani anapata ununuzi?',
            'example_note_en' => 'Each bidder also paid a separate participation fee when placing their bid.',
            'example_note_sw' => 'Kila mwekezaji pia alilipa ada ya ushiriki tofauti alipoweka zabuni yake.',
            'what_you_pay_title_en' => 'What you pay',
            'what_you_pay_title_sw' => 'Unacholipa',
            'what_you_pay_body_en' => 'Participation fee: paid now for each bid you place.',
            'what_you_pay_body_sw' => 'Ada ya ushiriki: inalipwa sasa kwa kila zabuni unayoweka.',
            'transparency_title_en' => 'Transparency',
            'transparency_title_sw' => 'Uwazi',
            'transparency_body_en' => 'Live bid counts and sale activity appear on auction pages.',
            'transparency_body_sw' => 'Idadi ya zabuni za moja kwa moja na shughuli za mauzo zinaonekana kwenye kurasa za mnada.',
        ]);
    }
}
