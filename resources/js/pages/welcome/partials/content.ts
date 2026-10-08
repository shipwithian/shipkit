/**
 * Placeholder copy for the landing page. Replace every string here with your
 * own; nothing else on the page needs to change.
 */
export const welcomeContent = {
    hero: {
        headline: 'Say what your product does in one sentence.',
        description:
            'Use this paragraph for the one thing a first-time visitor should understand: who the product is for and what gets easier once they have it.',
        screenshot: 'Product screenshot or illustration',
    },
    features: {
        heading: 'Give visitors three reasons to stay',
        items: [
            {
                title: 'Name the first benefit',
                description:
                    'Describe the outcome in plain words. Lead with what the visitor gets, not how it is built.',
            },
            {
                title: 'Name the second benefit',
                description:
                    'Pick something your closest alternative does not offer, and say it without comparing.',
            },
            {
                title: 'Name the third benefit',
                description:
                    'Answer the doubt that stops people signing up: price, effort, trust, or time.',
            },
        ],
    },
    callToAction: {
        heading: 'Close with the next step',
        description:
            'Repeat the single action you want a visitor to take, and say what happens right after they take it.',
    },
} as const;
