// Generic starter content for legacy-compatible public components.
// Published marketplace content and settings remain the source of truth.

function wiki(file, width = 1600) {
    return 'https://commons.wikimedia.org/wiki/Special:FilePath/'
        + encodeURIComponent(file)
        + '?width='
        + width;
}

export const heroSlides = [
    {
        image: wiki('Santorini sunset.jpg'),
        devanagari: '',
        title: 'Find a journey that fits your pace',
        subtitle: 'Explore places, stays, and experiences with clear information and flexible discovery.',
    },
    {
        image: wiki('Fushimi Inari Taisha, Kyoto.jpg'),
        devanagari: '',
        title: 'Go further with useful local context',
        subtitle: 'Build a more comfortable trip around the places and experiences you actually want to see.',
    },
    {
        image: wiki('Lisbon view from Miradouro da Senhora do Monte.jpg'),
        devanagari: '',
        title: 'Make room for memorable details',
        subtitle: 'Browse published journeys and shape the next part of your travel story.',
    },
];

export const categoryImages = {
    nature: wiki('Algarve coastline.jpg', 900),
    culture: wiki('Fushimi Inari Taisha, Kyoto.jpg', 900),
    city: wiki('Lisbon view from Miradouro da Senhora do Monte.jpg', 900),
    coast: wiki('Santorini sunset.jpg', 900),
    mountains: wiki('Swiss Alps.jpg', 900),
    default: wiki('Algarve coastline.jpg', 900),
};

export function categoryImage(category) {
    return categoryImages[category] || categoryImages.default;
}

export const dailyShlokas = [
    { sanskrit: 'Take the scenic route when time allows.', translation: 'A little room in the itinerary often creates the best travel memories.', source: 'Travel note' },
    { sanskrit: 'Leave space for the unexpected.', translation: 'The most useful plan balances structure with room to wander.', source: 'Travel note' },
    { sanskrit: 'Notice the details along the way.', translation: 'Neighbourhoods, food, and local rhythms give a destination its character.', source: 'Travel note' },
];

export const festivalGuides = [
    { name: 'Spring escapes', image: wiki('Algarve coastline.jpg', 900), blurb: 'Comfortable weather and longer days for exploring at an easy pace.', tag: 'Mar–May' },
    { name: 'Summer coastlines', image: wiki('Santorini sunset.jpg', 900), blurb: 'Plan early starts, relaxed afternoons, and a little time by the water.', tag: 'Jun–Aug' },
    { name: 'Autumn city breaks', image: wiki('Lisbon view from Miradouro da Senhora do Monte.jpg', 900), blurb: 'A thoughtful season for neighbourhood walks, culture, and food.', tag: 'Sep–Nov' },
];

export const culturalTrivia = [
    { question: 'What makes a good travel plan?', answer: 'Start with the places that matter most, then leave enough time to experience them without rushing.' },
    { question: 'How can a trip feel more comfortable?', answer: 'Group nearby places together, confirm practical details early, and keep transitions simple.' },
    { question: 'Why explore beyond the main sight?', answer: 'The surrounding streets, local food, and everyday routines often reveal the character of a destination.' },
];

export const festivalGuidesDetailed = [
    {
        name: 'A slower coastal weekend',
        image: wiki('Algarve coastline.jpg', 1200),
        window: 'Spring',
        description: 'Pair a walkable base with one or two carefully chosen day trips and let the landscape set the rhythm.',
        tips: ['Keep the first day light', 'Confirm local transport before setting out', 'Leave one unplanned afternoon'],
    },
    {
        name: 'A neighbourhood-led city break',
        image: wiki('Lisbon view from Miradouro da Senhora do Monte.jpg', 1200),
        window: 'Autumn',
        description: 'Choose a central base, explore on foot, and build the itinerary around food, culture, and local streets.',
        tips: ['Book time-sensitive visits ahead', 'Use public transport where it is practical', 'Ask locally for a quieter route'],
    },
];

export const contactInfo = {
    companyName: 'Travel marketplace',
    phone: null,
    phone2: null,
    phone2Href: null,
    phoneHref: null,
    marketingPhone: null,
    marketingPhoneHref: null,
    ceo: null,
    marketingHead: null,
    travelConsultants: [],
    email: null,
    website: null,
    websiteHref: null,
    headOffice: null,
    mapUrl: null,
    mapEmbedUrl: null,
    hours: null,
};

export const socialLinks = [];

export const whyChooseUs = [
    { icon: 'bi-compass', title: 'Clear discovery', text: 'Find useful destination and experience context before you decide.' },
    { icon: 'bi-calendar2-check', title: 'Flexible planning', text: 'Shape your journey around the time, places, and pace that suit you.' },
    { icon: 'bi-shield-check', title: 'Visible details', text: 'Published availability and practical information stay easy to compare.' },
    { icon: 'bi-headset', title: 'Helpful support', text: 'Use the configured contact channels when you need a hand.' },
];

export const attractions = [
    { name: 'Lisbon', image: wiki('Lisbon view from Miradouro da Senhora do Monte.jpg', 900), blurb: 'Layered streets, viewpoints, and a generous food culture.' },
    { name: 'Kyoto', image: wiki('Fushimi Inari Taisha, Kyoto.jpg', 900), blurb: 'A place to balance well-known landmarks with quieter neighbourhoods.' },
    { name: 'Algarve', image: wiki('Algarve coastline.jpg', 900), blurb: 'Coastal paths, open skies, and time to slow down.' },
    { name: 'The Alps', image: wiki('Swiss Alps.jpg', 900), blurb: 'Mountain landscapes for active days and restorative pauses.' },
];

export const bestTimeToVisit = [
    { period: 'Spring', weather: 'Mild', goodFor: 'Walking, gardens, and longer days' },
    { period: 'Summer', weather: 'Warm', goodFor: 'Coastlines, outdoor dining, and late evenings' },
    { period: 'Autumn', weather: 'Comfortable', goodFor: 'Cities, food, and cultural visits' },
    { period: 'Winter', weather: 'Cool', goodFor: 'Museums, markets, and quieter stays' },
];

export const galleryImages = [
    wiki('Algarve coastline.jpg', 700),
    wiki('Fushimi Inari Taisha, Kyoto.jpg', 700),
    wiki('Lisbon view from Miradouro da Senhora do Monte.jpg', 700),
    wiki('Santorini sunset.jpg', 700),
];

export const blogPosts = [];
export const faqItems = [];
export const reviewsSummary = { average: null, count: 0, breakdown: [] };

export const trustBadges = [
    { icon: 'bi-compass', label: 'Destination discovery' },
    { icon: 'bi-card-checklist', label: 'Clear travel details' },
    { icon: 'bi-headset', label: 'Configured support' },
    { icon: 'bi-shield-check', label: 'Secure customer flows' },
];
