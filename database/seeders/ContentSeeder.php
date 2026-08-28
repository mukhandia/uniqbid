<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\HowItWorksExampleRow;
use App\Models\HowItWorksSetting;
use App\Models\HowItWorksStep;
use App\Models\LegalClause;
use App\Models\LegalSection;
use App\Models\LegalSetting;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    /**
     * Seed CMS content for public pages and admin editing.
     *
     * Safe to run on every deploy: exits immediately when any testimonial
     * already exists, so admin edits are never overwritten on later seeds.
     */
    public function run(): void
    {
        if (Testimonial::query()->exists()) {
            $this->command->info('CMS content already present — skipping ContentSeeder.');

            return;
        }

        $this->seedTestimonials();
        $this->seedFaqs();
        $this->seedLegal();
        $this->seedHowItWorks();
    }

    private function seedTestimonials(): void
    {
        $items = [
            ['Amina Wanjiku', 'Nairobi', 'Successful buyer', 'Samsung 55" QLED TV', 'I secured a TV for less than the price of a month\'s data bundles. The countdown was clear, M-Pesa was instant, and the delivery team called the same day.', 5],
            ['Brian Otieno', 'Kisumu', 'Regular bidder', 'iPhone 15 Pro', 'Finally an auction site that shows the bid fee up front. I placed three bids, got WhatsApp receipts every time, and understood the outcome clearly when past sales went public.', 5],
            ['Faith Chebet', 'Eldoret', 'Shop customer', 'Leather Cardholder', 'Came for the auctions, stayed for the shop. Ordered a gift hamper for my sister: quality packaging and it arrived in Eldoret in two days.', 5],
            ['Daniel Kamau', 'Thika', 'Successful buyer', 'PlayStation 5 Bundle', 'I was sceptical about "lowest unique bid", but the worked example on How It Works made sense. Secured a PS5 for a fraction of retail. UniqBid feels legit.', 5],
            ['Grace Njeri', 'Mombasa', 'First-time bidder', 'Dyson Airwrap', 'Support replied on WhatsApp within an hour when I mistyped my bid amount. They walked me through the rules without pressure. That trust made me place another bid.', 4],
            ['Joseph Mutua', 'Nakuru', 'Successful buyer', 'MacBook Air M3', 'The public past sales ledger sold me. I checked earlier sales before bidding and saw real prices. After my successful buy, collection at their Nairobi depot was smooth.', 5],
        ];

        foreach ($items as $index => [$name, $location, $role, $item, $quote, $rating]) {
            Testimonial::query()->create([
                'name' => $name,
                'location' => $location,
                'role' => $role,
                'item' => $item,
                'quote' => $quote,
                'rating' => $rating,
                'sort_order' => $index + 1,
                'is_published' => true,
            ]);
        }
    }

    private function seedFaqs(): void
    {
        $items = [
            [Faq::CATEGORY_BIDDING, 'What is a lowest unique bid?', 'At close, duplicate bid amounts are excluded. The lowest remaining unique amount secures the item.', 'Zabuni ya chini kabisa isiyo na mfano ni nini?', 'Mwisho wa mauzo, kiasi cha zabuni kinachorudiwa kinatolewa. Kiasi cha chini kabisa kinachobaki bila mfano ndicho kinachopata bidhaa.'],
            [Faq::CATEGORY_BIDDING, 'What is the participation fee?', 'The participation fee is paid now to confirm each bid. It is separate from your bid amount and is not credited against the sale price.', 'Ada ya ushiriki ni nini?', 'Ada ya ushiriki inalipwa sasa kuthibitisha kila zabuni. Ni tofauti na kiasi cha zabuni yako na haijawekwa kwenye bei ya mauzo.'],
            [Faq::CATEGORY_SHOP, 'Can I buy from the shop without bidding?', 'Yes. Browse the shop, add items to your cart, and checkout with Nairobi delivery, Westlands collection, or upcountry delivery.', 'Naweza kununua kutoka dukani bila kuweka zabuni?', 'Ndiyo. Vinjari duka, ongeza bidhaa kwenye kikapu chako, na maliza ununuzi kwa uwasilishaji Nairobi, ukusanyaji Westlands, au nje ya Nairobi.'],
            [Faq::CATEGORY_ACCOUNT, 'How do I verify my account?', 'Sign in with your mobile number and OTP. For ID or KRA verification, contact support and we will guide you through the process.', 'Ninathibitishaje akaunti yangu?', 'Ingia kwa nambari yako ya simu na OTP. Kwa uthibitisho wa kitambulisho au KRA, wasiliana na msaada na tutakuongoza.'],
            [Faq::CATEGORY_GENERAL, 'Where is UniqBid based?', 'UniqBid Auction Limited is registered in Westlands, Nairobi, Kenya and operates under a licensed auctioneer framework.', 'UniqBid iko wapi?', 'UniqBid Auction Limited imesajiliwa Westlands, Nairobi, Kenya na inafanya kazi chini ya mfumo wa mnada wenye leseni.'],
        ];

        foreach ($items as $index => [$category, $questionEn, $answerEn, $questionSw, $answerSw]) {
            Faq::query()->create([
                'category' => $category,
                'question_en' => $questionEn,
                'answer_en' => $answerEn,
                'question_sw' => $questionSw,
                'answer_sw' => $answerSw,
                'sort_order' => $index + 1,
                'is_published' => true,
            ]);
        }
    }

    private function seedLegal(): void
    {
        LegalSetting::query()->create([
            'version' => '2.1',
            'effective_date' => '1 July 2026',
        ]);

        $sections = [
            ['terms', 'Terms & Conditions', [
                ['1.1 Parties and acceptance', 'These Terms are a binding agreement between UniqBid Auction Limited, of Westlands, Nairobi, and each person who registers, places a bid, or purchases an item through the platform. By using the platform, you confirm that you are at least 18, act on your own behalf, and accept these Terms, the Selling Rules, Privacy Policy, and Refund Policy.'],
                ['1.2 Eligibility and account integrity', 'One natural person may hold one account. We may request national ID or KRA PIN verification to prevent duplicate accounts, underage use, fraud, or bid manipulation. You are responsible for your account activity and must report unauthorised access to support@uniqbid.co.ke.'],
                ['1.3–1.5 Platform role and responsible participation', 'A bid does not guarantee a successful outcome. Bid fees are participation costs, not an investment or income source. Users may set daily or monthly spend limits and request self-exclusion through support for a minimum of 30 days.'],
                ['1.6–1.9 Intellectual property, liability, disputes and changes', 'Platform content is owned by or licensed to UniqBid. Our aggregate liability is limited to payments made in the three months before a claim, where law permits. Raise disputes with support first; unresolved disputes may be mediated in Nairobi under Kenyan law. We give at least 7 days’ notice of material changes.'],
            ]],
            ['rules', 'Selling Rules', [
                ['2.1 Lowest-unique-bid sale mechanism', 'Each sale lists an item, a whole-KES bid range, and a fixed increment. At close, duplicate bid amounts are excluded; the lowest remaining unique amount secures the item. If no unique amount exists, the published tie-break rule applies.'],
                ['2.2–2.3 Placing a bid and bid fees', 'Each bid costs the displayed bid fee and is counted only after payment and a reference are confirmed. Bids cannot be edited or cancelled after confirmation. Bid fees are separate from the sale price and are not credited against it.'],
                ['2.4–2.5 Sale close, results and odds', 'Sales close according to the displayed East Africa Time server clock. Results, including the successful bid and a masked identifier, are published within 24 hours. The successful buyer has seven days to pay the sale amount and confirm delivery. Bid count, distinct unique amounts, and an indicative probability statement are displayed for transparency.'],
                ['2.6–2.8 Ties, void sales and prohibited conduct', 'The sale listing states whether no-unique-bid sales are extended, awarded by the fewest duplicates, or voided. We may void, extend, or re-run a sale for errors, manipulation, unavailable items, or regulatory requirements. Bots, multiple accounts, collusion, and attempts to manipulate the mechanism are prohibited.'],
                ['2.9 Item condition and delivery', 'Items are new unless stated otherwise and must be accurately described. Delivery within Nairobi and Westlands collection are available. Delivery fee charges apply outside Nairobi and are confirmed at collection or delivery.'],
            ]],
            ['privacy', 'Privacy Policy', [
                ['3.1–3.2 Controller and data collected', 'UniqBid Auction Limited is the data controller under the Data Protection Act, 2019. We collect identity, contact, transaction, technical, and support-communication data needed to operate the platform. We do not knowingly collect data from people under 18.'],
                ['3.3–3.6 Purpose, payments and sharing', 'We process data to record bids, process payments, fulfil orders, prevent fraud, meet legal duties, and, only with consent, send marketing. We receive payment confirmation data but never M-Pesa PINs. Data is shared only with payment, delivery, hosting, and legally authorised service providers.'],
                ['3.7–3.11 Retention, rights, automation and cookies', 'Transaction records are retained for seven years; inactive account data for three years unless a longer period is required. You may request access, correction, erasure where permitted, restriction, objection, portability, or human review of a sale result. Essential cookies operate the site; analytics and marketing cookies require consent. Contact dpo@uniqbid.co.ke or support@uniqbid.co.ke.'],
            ]],
            ['refunds', 'Refund Policy', [
                ['4.1–4.3 Participation and sale payments', 'Bid fees are non-refundable except for voided sales, erroneous charges, or where required by law. A sale price paid for a cancelled sale is refunded in full within seven business days, subject to the Cancellation of Sale Policy.'],
                ['4.4–4.8 Shop returns and refund process', 'Direct shop purchases may be returned within seven days of delivery if unused, in original packaging, and supported by proof of purchase; exclusions apply to perishables, customised goods, and clearly marked non-returnable items. Faulty, damaged, or materially misdescribed goods may qualify for refund or replacement. Contact support with the order or sale reference and M-Pesa code. Approved refunds return to the original payment method.'],
            ]],
            ['cancellation', 'Cancellation of Sale Policy', [
                ['5.1–5.4 When a sale can be cancelled', 'We may cancel a sale for item unavailability, a genuine listing error, failed age or identity verification, credible fraud, non-payment within the published period, or a regulatory requirement. Before dispatch, shop purchases may be cancelled without charge; after dispatch, return rights apply.'],
                ['5.5–5.7 Effect and records', 'Where we cancel for a reason other than your non-payment or confirmed fraud, sale price and delivery fees already paid are refunded within seven business days. We may re-offer an item after non-payment, fraud, or an accepted cancellation. Cancellation records are retained with transaction records for seven years.'],
            ]],
            ['clearance-house', 'Corporate Seller Accounts', [
                ['6.1–6.7 UniqBid Clearance House', 'Clearance House is a programme for registered companies selling verified returns, overstock, or liquidation inventory. Sellers must provide incorporation, KRA, payout, ownership, and item-condition evidence. Stolen, counterfeit, unsafe, recalled, or inaccurately described goods are prohibited. Buyer protections remain unchanged.'],
            ]],
            ['licence', 'Licence Information', [
                ['7.1–7.6 Corporate and regulatory information', 'UniqBid Auction Limited is a Kenyan company with its registered office in Westlands, Nairobi. It holds auctioneer licence AUC/2024/00847 under the Auctioneers Act (Cap 526). The platform sells genuine goods through a transparent, rule-based competitive-sale mechanism; it is not a gambling, betting, or gaming service. Data-handling complaints may be referred to the ODPC, and consumer or auctioneering complaints to the relevant Kenyan regulators.'],
            ]],
        ];

        foreach ($sections as $sectionIndex => [$slug, $title, $clauses]) {
            $section = LegalSection::query()->create([
                'slug' => $slug,
                'title' => $title,
                'sort_order' => $sectionIndex + 1,
                'is_published' => true,
            ]);

            foreach ($clauses as $clauseIndex => [$reference, $body]) {
                LegalClause::query()->create([
                    'legal_section_id' => $section->id,
                    'reference' => $reference,
                    'title' => $reference,
                    'body' => $body,
                    'sort_order' => $clauseIndex + 1,
                ]);
            }
        }
    }

    private function seedHowItWorks(): void
    {
        HowItWorksSetting::query()->create([
            'page_title_en' => 'How UniqBid works',
            'page_title_sw' => 'UniqBid Inavyofanya Kazi',
            'intro_en' => 'The lowest unique bid secures the item. Unique means no one else placed exactly that amount. Here\'s the whole idea in one page.',
            'intro_sw' => 'Zabuni ya chini kabisa isiyo na mfano ndiyo inayopata bidhaa. Hii ndio dhana yote katika ukurasa mmoja.',
            'example_heading_en' => 'A worked example',
            'example_heading_sw' => 'Mfano wa kazi',
            'example_intro_en' => 'Five people bid on the same item. Who secures the buy?',
            'example_intro_sw' => 'Watu watano wanaweka zabuni kwenye bidhaa moja. Nani anapata ununuzi?',
            'example_note_en' => 'Each bidder also paid a separate participation fee when placing their bid. Bidder C then pays {amount} on collection or delivery, not the product’s retail value. A and B tied at KES 15, so those bids were not unique.',
            'example_note_sw' => 'Kila mwekezaji pia alilipa ada ya ushiriki tofauti alipoweka zabuni yake. Mwekezaji C analipa {amount} ukusanyaji au uwasilishaji, si thamani ya rejareja ya bidhaa. A na B walilingana kwa KES 15, hivyo zabuni hizo hazikuwa za kipekee.',
            'what_you_pay_title_en' => 'What you pay',
            'what_you_pay_title_sw' => 'Unacholipa',
            'what_you_pay_body_en' => "<strong>Participation fee:</strong> paid now for each bid you place.<br>\n<strong>Bid amount:</strong> the number you enter in the range; paid later only if it is the successful bid.<br>\n<strong>Product value:</strong> shown for comparison only; never charged as a bid payment.",
            'what_you_pay_body_sw' => "<strong>Ada ya ushiriki:</strong> inalipwa sasa kwa kila zabuni unayoweka.<br>\n<strong>Kiasi cha zabuni:</strong> nambari unayoweka ndani ya masafa; inalipwa baadaye tu ikiwa ndiyo zabuni iliyofanikiwa.<br>\n<strong>Thamani ya bidhaa:</strong> inaonyeshwa kwa kulinganisha tu; haijalipwi kama malipo ya zabuni.",
            'transparency_title_en' => 'Transparency',
            'transparency_title_sw' => 'Uwazi',
            'transparency_body_en' => 'Live bid counts and sale activity appear on auction pages. Past sales are published publicly. This is a products-selling platform, not gambling.',
            'transparency_body_sw' => 'Idadi ya zabuni za moja kwa moja na shughuli za mauzo zinaonekana kwenye kurasa za mnada. Mauzo ya zamani yanachapishwa hadharani. Hii ni jukwaa la kuuza bidhaa, si kamari.',
        ]);

        $steps = [
            [1, 'Browse', 'Vinjari', 'See product value, the bid range, and the small participation fee for each sale.', 'Angalia thamani ya bidhaa, masafa ya zabuni, na ada ndogo ya ushiriki kwa kila mauzo.'],
            [2, 'Enter a bid amount', 'Weka kiasi cha zabuni', 'Choose any amount inside the range. You can place multiple bids at different amounts.', 'Chagua kiasi chochote ndani ya masafa. Unaweza kuweka zabuni nyingi kwa viwango tofauti.'],
            [3, 'Pay participation fee', 'Lipa ada ya ushiriki', 'Pay the participation fee now to confirm that bid. It is separate from your bid amount.', 'Lipa ada ya ushiriki sasa kuthibitisha zabuni hiyo. Ni tofauti na kiasi cha zabuni yako.'],
            [4, 'Successful buy', 'Ununuzi uliofanikiwa', 'At close, the lowest unique bid secures the item. That buyer then pays their bid amount on collection or delivery.', 'Mwisho wa mauzo, zabuni ya chini kabisa isiyo na mfano ndiyo inayopata bidhaa. Mnunuzi huyo analipa kiasi cha zabuni yake ukusanyaji au uwasilishaji.'],
        ];

        foreach ($steps as $index => [$number, $titleEn, $titleSw, $descriptionEn, $descriptionSw]) {
            HowItWorksStep::query()->create([
                'step_number' => $number,
                'title_en' => $titleEn,
                'title_sw' => $titleSw,
                'description_en' => $descriptionEn,
                'description_sw' => $descriptionSw,
                'sort_order' => $index + 1,
            ]);
        }

        $rows = [
            ['Bidder A', 15, false],
            ['Bidder B', 15, false],
            ['Bidder C', 22, true],
            ['Bidder D', 41, true],
            ['Bidder E', 50, true],
        ];

        foreach ($rows as $index => [$name, $amount, $isUnique]) {
            HowItWorksExampleRow::query()->create([
                'name' => $name,
                'amount' => $amount,
                'is_unique' => $isUnique,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
