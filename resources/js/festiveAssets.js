// ---------------------------------------------------------------------------
// Placeholder imagery — sourced from Wikimedia Commons under CC BY-SA / CC BY
// licenses (free for commercial use with attribution). Swap these for your
// own photography before launch; keep the same variable names so nothing
// else needs to change.
//
// Attribution (keep a line like this in your site footer / credits page):
//   Temple & festival photography via Wikimedia Commons contributors,
//   licensed CC BY-SA 3.0 / 4.0 and CC BY 2.0.
// ---------------------------------------------------------------------------

function wiki(file, width = 1600) {
    return `https://commons.wikimedia.org/wiki/Special:FilePath/${encodeURIComponent(file)}?width=${width}`;
}

export const heroSlides = [
    {
        image: wiki('Prem mandir Vrindavan.JPG'),
        devanagari: '॥ राधे राधे ॥',
        title: 'Journey to the Land of Shree Krishna',
        subtitle: 'Guided pilgrimages through Vrindavan, Mathura, Gokul, Barsana and Govardhan — with transparent pricing and real online booking.',
    },
    {
        image: wiki('Bankebihari temple main gate Vrindavan.JPG'),
        devanagari: '॥ जय श्री बांके बिहारी ॥',
        title: 'Darshan at the Temples of Braj Bhoomi',
        subtitle: 'Skip the confusion — curated temple-trail itineraries with local guides who know every gali of Vrindavan.',
    },
    {
        image: wiki('Lathmar Holi 2022 in Nandgaon, Uttar Pradesh.jpg'),
        devanagari: '॥ होली है ॥',
        title: 'Feel the Colours of Braj ki Holi',
        subtitle: 'Lathmar Holi in Barsana, Phoolon Wali Holi in Vrindavan — book your festival-season package early.',
    },
    {
        image: wiki('Ganga aarti haridwar 02.jpg'),
        devanagari: '॥ हर हर गंगे ॥',
        title: 'Extend Your Yatra to Haridwar & Rishikesh',
        subtitle: 'Combine your Braj darshan with the Ganga aarti at Har Ki Pauri on our Uttarakhand circuit.',
    },
];

export const categoryImages = {
    braj: wiki('Prem mandir Vrindavan.JPG', 900),
    temple: wiki('Bankebihari temple main gate Vrindavan.JPG', 900),
    up_circuit: wiki('Taj-Mahal.jpg', 900),
    rajasthan: wiki('Taj-Mahal.jpg', 900),
    uttarakhand: wiki('Ganga aarti haridwar 02.jpg', 900),
    festival: wiki('Lathmar Holi 2022 in Nandgaon, Uttar Pradesh.jpg', 900),
};

export function categoryImage(category) {
    return categoryImages[category] || categoryImages.braj;
}

export const dailyShlokas = [
    {
        sanskrit: 'ॐ नमो भगवते वासुदेवाय',
        translation: 'Salutations to Lord Vasudeva, the all-pervading divine consciousness.',
        source: 'Vaishnava Mahamantra',
    },
    {
        sanskrit: 'हरे कृष्ण हरे कृष्ण कृष्ण कृष्ण हरे हरे । हरे राम हरे राम राम राम हरे हरे ॥',
        translation: 'The Maha Mantra — chanted for the remembrance and glorification of Radha-Krishna.',
        source: 'Kali-Santarana Upanishad',
    },
    {
        sanskrit: 'यदा यदा हि धर्मस्य ग्लानिर्भवति भारत । अभ्युत्थानमधर्मस्य तदात्मानं सृजाम्यहम् ॥',
        translation: 'Whenever righteousness declines, I manifest myself to restore dharma.',
        source: 'Bhagavad Gita 4.7',
    },
    {
        sanskrit: 'गोविन्दं आदिपुरुषं तमहं भजामि',
        translation: 'I worship Govinda, the original, primeval Lord.',
        source: 'Brahma Samhita 5.1',
    },
    {
        sanskrit: 'वृन्दावनं परित्यज्य पादमेकं न गच्छति',
        translation: 'Krishna never leaves Vrindavan, not even by a single step.',
        source: 'Traditional Braj saying',
    },
];

export const festivalGuides = [
    {
        name: 'Lathmar Holi, Barsana',
        image: wiki('Lathmar Holi 2022 in Nandgaon, Uttar Pradesh.jpg', 900),
        blurb: 'Women playfully strike men with sticks in Radha Rani\'s hometown — the most electric Holi celebration in India.',
        tag: 'Feb–Mar',
    },
    {
        name: 'Phoolon Wali Holi, Vrindavan',
        image: wiki('Bankebihari temple main gate Vrindavan.JPG', 900),
        blurb: 'Priests shower devotees with flower petals at Banke Bihari Temple — no colour, just fragrance and devotion.',
        tag: 'Feb–Mar',
    },
    {
        name: 'Janmashtami, Mathura & Vrindavan',
        image: wiki('Prem mandir Vrindavan.JPG', 900),
        blurb: 'Krishna\'s birthplace comes alive with midnight aarti, Dahi Handi and temple decorations across Braj.',
        tag: 'Aug–Sep',
    },
];

export const culturalTrivia = [
    {
        question: 'Why is Krishna often shown with a peacock feather?',
        answer: 'The peacock feather (mor-pankh) symbolises grace and beauty. Legend holds Krishna wore one Radha gave him during their time in the Vrindavan forests, and it became one of his defining emblems.',
    },
    {
        question: 'What does "Radhe Radhe" mean as a greeting?',
        answer: 'It invokes Radha, Krishna\'s eternal consort, and is the everyday greeting across Braj — used instead of "namaste" in Vrindavan, Mathura, Barsana and Nandgaon.',
    },
    {
        question: 'What is Vrindavan known as, poetically?',
        answer: 'The "Land of Kunj" — kunj means a grove or bower. Vrindavan\'s forests, especially Nidhivan and Seva Kunj, are believed to be where Krishna performed his Raas Leela with Radha and the gopis.',
    },
    {
        question: 'What is a "parikrama"?',
        answer: 'A circumambulation — walking a sacred circuit around a temple, town, or hill (like the 21 km Govardhan Parikrama) as an act of devotion.',
    },
];

export const festivalGuidesDetailed = [
    {
        name: 'Lathmar Holi, Barsana & Nandgaon',
        image: wiki('Lathmar Holi 2022 in Nandgaon, Uttar Pradesh.jpg', 1200),
        window: 'Late Feb – Early Mar',
        description: 'Women of Barsana (Radha\'s hometown) playfully strike men from Nandgaon with sticks while they shield themselves — a joyful re-enactment of Krishna\'s teasing of Radha and her friends.',
        tips: ['Book Barsana & Nandgaon accommodation weeks ahead — it sells out fast', 'Wear clothes you don\'t mind staining', 'Arrive early morning for the best spot near the temple courtyard'],
    },
    {
        name: 'Phoolon Wali Holi, Vrindavan',
        image: wiki('Bankebihari temple main gate Vrindavan.JPG', 1200),
        window: 'Day before Holi',
        description: 'At Banke Bihari Temple, priests shower the crowd with fresh flower petals for 15–20 minutes — no colour, just fragrance, chanting and devotion.',
        tips: ['Reach the temple at least an hour before the scheduled time', 'Keep hands free to catch petals as prasad', 'Pair it with darshan at Radha Vallabh Temple nearby'],
    },
    {
        name: 'Janmashtami, Mathura & Vrindavan',
        image: wiki('Prem mandir Vrindavan.JPG', 1200),
        window: 'Aug – Sep',
        description: 'Krishna\'s birthplace comes alive at midnight with elaborate aarti, cradle ceremonies and Dahi Handi, drawing devotees from across the country to Krishna Janmabhoomi and the major temples.',
        tips: ['Midnight darshan queues can run long — plan your evening around it', 'Prem Mandir\'s light-and-sound show is worth catching', 'Sattvik/vegetarian food only during the fasting period'],
    },
    {
        name: 'Ganga Dussehra, Haridwar',
        image: wiki('Ganga aarti haridwar 02.jpg', 1200),
        window: 'May – Jun',
        description: 'Marks the descent of the Ganga to earth. Devotees take a holy dip at Har Ki Pauri and attend the evening Ganga Aarti — a natural extension if you\'re combining Braj with the Uttarakhand circuit.',
        tips: ['Evening aarti gets crowded — arrive 45 minutes early for a ghat-side spot', 'Combine with Rishikesh for a 2-day extension'],
    },
];

export const contactInfo = {
    companyName: 'Shree Vrindavan Tour Packages',
    phone: '+91 89234 27393',
    phoneHref: 'tel:+918923427393',
    marketingPhone: '+91 70176 21518',
    marketingPhoneHref: 'tel:+917017621518',
    ceo: 'Kanhaiya Upadhyay',
    marketingHead: 'Narendra Sharma',
    travelConsultants: [
        { name: 'Abhishek Upadhyay', phone: '844550786', phoneHref: 'tel:844550786' },
        { name: 'Lavi Upadhyay', phone: '8979020415', phoneHref: 'tel:+918979020415' },
    ],
    email: 'info@shreevrindavantourpackages.com',
    website: 'www.shreevrindavantourpackages.com',
    websiteHref: 'https://www.shreevrindavantourpackages.com',
    headOffice: 'Behind ATV, Near Pawan Kunj, Jay Gurudev Temple, Mathura, Uttar Pradesh.',
    mapUrl: 'https://maps.app.goo.gl/hvnfoqfjsMf4D5748',
    mapEmbedUrl: 'https://www.google.com/maps?q=27.4748346%2C77.6192256&z=17&output=embed',
    hours: 'Support 7:00 AM – 10:00 PM',
};

export const socialLinks = [
    { icon: 'bi-facebook', href: '#', label: 'Facebook' },
    { icon: 'bi-instagram', href: '#', label: 'Instagram' },
    { icon: 'bi-youtube', href: '#', label: 'YouTube' },
    { icon: 'bi-whatsapp', href: 'https://wa.me/918923427393', label: 'WhatsApp' },
];

export const whyChooseUs = [
    { icon: 'bi-clock-history', title: 'Darshan-First Planning', text: 'Routes built around temple timings and crowd flow — not a rushed tourist checklist.' },
    { icon: 'bi-signpost-2', title: 'Local Braj Routes', text: 'Alternate lanes, festival diversions and parking know-how our drivers have used for years.' },
    { icon: 'bi-shield-check', title: 'Family & Senior Friendly', text: 'Clean vehicles, calm driving and proper support for elders and children.' },
    { icon: 'bi-cash-coin', title: 'Transparent Pricing', text: 'What you see is what you pay — no hidden guide fees or surprise parking charges.' },
];

export const attractions = [
    { name: 'Banke Bihari Temple', image: wiki('Bankebihari temple main gate Vrindavan.JPG', 900), blurb: 'Vrindavan\'s most-loved temple — famous for its curtain darshan, closed every few seconds so devotees don\'t linger too long before the deity.' },
    { name: 'Prem Mandir', image: wiki('Prem mandir Vrindavan.JPG', 900), blurb: 'A marble masterpiece lit in changing colours at night, its carvings retelling scenes from Krishna\'s life.' },
    { name: 'ISKCON Vrindavan', image: wiki('Prem mandir Vrindavan.JPG', 900), blurb: 'A striking white-marble temple complex known worldwide for its kirtans and welcoming atmosphere.' },
    { name: 'Yamuna Ghats', image: wiki('Ganga aarti haridwar 02.jpg', 900), blurb: 'Keshi Ghat is the spot for a sunset boat ride and a quieter view of the town away from the main streets.' },
];

export const bestTimeToVisit = [
    { period: 'Oct – Dec', weather: 'Cool, 15–25°C', goodFor: 'Sightseeing, temple visits & long walks' },
    { period: 'Jan – Mar', weather: 'Pleasant', goodFor: 'Holi celebrations & spring festivals' },
    { period: 'Apr – Jun', weather: 'Hot, 35–45°C', goodFor: 'Budget travel, less crowded darshan (AC cabs recommended)' },
    { period: 'Jul – Sep', weather: 'Monsoon, green & humid', goodFor: 'Lush scenery, Janmashtami season' },
];

export const galleryImages = [
    wiki('Prem mandir Vrindavan.JPG', 700),
    wiki('Bankebihari temple main gate Vrindavan.JPG', 700),
    wiki('Lathmar Holi 2022 in Nandgaon, Uttar Pradesh.jpg', 700),
    wiki('Ganga aarti haridwar 02.jpg', 700),
    wiki('Taj-Mahal.jpg', 700),
    wiki('A night view of the Love Temple Vrindavan India 2015.jpg', 700),
];

export const blogPosts = [
    { title: 'A First-Timer\'s Guide to Vrindavan Darshan', excerpt: 'What to wear, when to go, and the temple sequence that saves you the most walking.', image: wiki('Bankebihari temple main gate Vrindavan.JPG', 800), date: 'Dummy content — replace with a real post' },
    { title: '5 Things Nobody Tells You About Braj Holi', excerpt: 'From Lathmar Holi timing to what to actually wear so the colour washes out easily.', image: wiki('Lathmar Holi 2022 in Nandgaon, Uttar Pradesh.jpg', 800), date: 'Dummy content — replace with a real post' },
    { title: 'Beyond the Big Temples: Quiet Corners of Vrindavan', excerpt: 'Nidhivan, Seva Kunj and the early-morning Keshi Ghat — for travellers who want stillness.', image: wiki('Prem mandir Vrindavan.JPG', 800), date: 'Dummy content — replace with a real post' },
];

export const faqItems = [
    { q: 'What is included in your tour packages?', a: 'Most packages include a private AC cab, driver support, route planning, pickup/drop and guidance on temple sequence. Exact inclusions vary by duration and vehicle — check each package\'s inclusions list.' },
    { q: 'Do you arrange pickup from other cities?', a: 'Yes — we can arrange pickup from Delhi, Agra, Jaipur and other nearby cities. Mention your pickup city when enquiring and we\'ll quote accordingly.' },
    { q: 'Is this suitable for senior citizens?', a: 'Yes. We plan comfortable breaks, minimise unnecessary walking, and can suggest a senior-friendly temple order.' },
    { q: 'Can I customise my itinerary?', a: 'Absolutely — share your dates, number of people and must-visit temples, and we\'ll put together a realistic route.' },
];

export const reviewsSummary = { average: 4.8, count: 180, breakdown: [ { stars: 5, pct: 84 }, { stars: 4, pct: 12 }, { stars: 3, pct: 3 }, { stars: 2, pct: 1 }, { stars: 1, pct: 0 } ] };

export const trustBadges = [
    { icon: 'bi-patch-check-fill', label: 'Govt. Registered Tour Operator' },
    { icon: 'bi-shield-lock-fill', label: 'Secure Online Payments' },
    { icon: 'bi-headset', label: '24/7 WhatsApp Support' },
    { icon: 'bi-star-fill', label: '4.8/5 Average Rating' },
    { icon: 'bi-people-fill', label: '50,000+ Pilgrims Guided' },
    { icon: 'bi-award-fill', label: 'Local Braj Guides' },
];
