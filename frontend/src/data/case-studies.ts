/**
 * CASE-STUDY CONTENT — VERIFIED ONLY (docs/08_CASE_STUDY_SPECIFICATION.md §3)
 * Every string is Master-verified copy (docs/DESIGN.md Sec. 08, Phase 05–08
 * section lists, docs/07_PROJECT_CONTENT.md). No metrics, no invented features,
 * no technology claims (Blocker 04: generic wording until D-009).
 * Technology copy: FINAL per D-009 (2026-09-21) — "Custom Web Application" wording
 * authorized as final; stacks revisitable per project if later verified.
 * Assets: ALL FOUR projects carry verified owner-provided screenshots (B-002 RESOLVED
 * 2026-09-22 — PCT 6 screens, TBMS 8 screens, EstateHub 6 unique screens, TravelNest 7
 * screens). Every carousel image is a real capture of the actual product.
 */

export type CaseStudySection = {
  heading: string;
  /** Verified paragraph copy. */
  body?: string;
  /** Verified bullet points (e.g., approach capabilities). */
  bullets?: string[];
  /** Asset slot label — renders a clearly marked pending placeholder (B-002). */
  assetLabel?: string;
};

/** A real product screenshot — owner-provided only; never generated or mocked. */
export type ShowcaseImage = {
  src: string;
  /** Descriptive alt/caption — names only what is visible on the screen itself. */
  alt: string;
};

export type CaseStudy = {
  slug: string;
  name: string;
  fullPositioning: string;
  /** Verified SEO title core (Master Sec. 22 pattern) — suffix added by the single layout template. */
  seoTitle: string;
  headline: string;
  description: string;
  metadata: string[];
  /** Product-structure visualization (Master-verified flow). */
  flow: string[];
  sections: CaseStudySection[];
  /** Optional verified result copy (PCT only — non-numerical). */
  result?: string;
  /** Verified screenshots (B-002) — present only when real owner-provided assets exist. */
  showcase?: { images: ShowcaseImage[] };
  projectUrl: string;
};

export const CASE_STUDIES: CaseStudy[] = [
  {
    slug: "pct",
    name: "PCT",
    fullPositioning: "Permetheon Command Terminal",
    /** Verified SEO title pattern (Master Sec. 22) — already includes the " | Permetheon" suffix. */
    seoTitle: "PCT — Permetheon Command Terminal",
    headline: "One command center for the teams building Permetheon.",
    description:
      "PCT is Permetheon's internal digital platform for managing projects and coordinating developers, designers and other teams from one centralized environment.",
    metadata: ["Internal Platform", "Project Management", "Team Operations", "Custom Software"],
    flow: ["PCT", "Projects", "Teams", "Tasks", "Reviews", "Activity", "Reports"],
    sections: [
      {
        heading: "The Challenge",
        body: "As projects grow, teams need more than scattered conversations, documents and task lists. PCT was designed to bring project execution and team operations into one structured environment.",
      },
      {
        heading: "The Business Context",
        body: "PCT serves Permetheon's own developers, designers and other teams — the people building Permetheon's products — as their day-to-day operational environment.",
      },
      {
        heading: "The Approach",
        body: "Create a centralized command layer where teams can:",
        bullets: [
          "Organize projects",
          "Manage tasks",
          "Coordinate teams",
          "Review work",
          "Track activity",
          "Monitor operational information",
        ],
      },
      {
        heading: "The Product",
        body: "Real screenshots from the implemented platform — dashboard, projects, tasks, reviews, notifications and activity — featured above.",
      },
      {
        heading: "UX / UI",
        body: "The interface was designed for operational clarity, fast navigation, team visibility, structured workflows — and high information density without visual clutter.",
      },
      {
        heading: "Technology",
        body: "Custom Web Application. Built as an internal platform tailored to Permetheon's operational needs.",
      },
    ],
    showcase: {
      images: [
        // Story order per the actual product flow (nav order of the verified screens):
        // main experience → project management → task workflow → reviews → notifications → activity.
        {
          src: "/screenshots/pct/dashboard.png",
          alt: "Dashboard — workspace overview with active tasks, reviews, projects and quick actions",
        },
        {
          src: "/screenshots/pct/projects.png",
          alt: "Projects — all projects with status, owner, team, progress and deadlines",
        },
        {
          src: "/screenshots/pct/tasks.png",
          alt: "Tasks — workspace task list with status, priority, project and assignee filters",
        },
        {
          src: "/screenshots/pct/reviews.png",
          alt: "Reviews — review decisions with status, developer, reviewer and attempt tracking",
        },
        {
          src: "/screenshots/pct/notifications.png",
          alt: "Notifications — single inbox for updates, mentions and review requests",
        },
        {
          src: "/screenshots/pct/activity.png",
          alt: "Activity — audit trail of changes across projects, tasks and reviews",
        },
      ],
    },
    result: "A centralized operational environment for Permetheon's internal project execution.",
    projectUrl: "https://pct.permetheon.com/",
  },
  {
    slug: "tbms",
    name: "TBMS",
    fullPositioning: "Restaurant Reservation & Table Management System",
    seoTitle: "TBMS — Restaurant Reservation & Table Management System",
    headline: "Turning restaurant reservations into a connected digital workflow.",
    description:
      "TBMS combines customer booking with restaurant-side administration and table management.",
    metadata: ["Restaurant", "Reservation System", "Booking Portal", "Admin Platform"],
    flow: ["Customer", "Booking Portal", "Reservation", "Restaurant Admin", "Table Management"],
    sections: [
      {
        heading: "The Challenge",
        body: "Restaurants need to manage incoming reservations while keeping table availability and operational information organized.",
      },
      {
        heading: "The Solution",
        body: "TBMS creates two connected experiences — a customer experience where customers interact with the booking/reservation flow, and a restaurant experience where restaurant teams manage reservations, tables and operational information through the admin portal.",
      },
      {
        heading: "The Customer Booking Experience",
        body: "A customer-facing booking site: table reservation entry points, the restaurant's story and featured dining, opening hours and location — with staff sign-in leading to the management side.",
      },
      {
        heading: "The Restaurant Admin Experience",
        body: "The restaurant-side management environment: a reservation dashboard, reservation records with guest and table detail, table management and customer records in one admin interface.",
      },
      {
        heading: "Table & Reservation Management",
        body: "Reservations carry guest, party, table and status detail; tables are managed with seat counts, floor locations and booking-availability controls.",
      },
      {
        heading: "Operational Workflow",
        body: "The customer booking flow connects directly to restaurant-side administration — reservations, table availability and operational control in one system.",
      },
      {
        heading: "UX / UI",
        body: "Two audiences, one coherent system: a clear booking experience for customers and an operational environment for restaurant teams.",
      },
      {
        heading: "Technology",
        body: "Custom Web Application with dedicated customer and administrative experiences.",
      },
    ],
    showcase: {
      images: [
        // Customer experience first, restaurant-side second (verified two-experience story):
        {
          src: "/screenshots/tbms/customer-home.png",
          alt: "Customer booking site — restaurant homepage with table reservation entry points",
        },
        {
          src: "/screenshots/tbms/customer-dining.png",
          alt: "Customer booking site — restaurant profile with seasonal menu and wine cellar highlights",
        },
        {
          src: "/screenshots/tbms/customer-window-tables.png",
          alt: "Customer booking site — featured dining section highlighting window tables",
        },
        {
          src: "/screenshots/tbms/customer-hours-location.png",
          alt: "Customer booking site — opening hours, location and reservation contact with staff sign-in",
        },
        {
          src: "/screenshots/tbms/admin-dashboard.png",
          alt: "Restaurant admin — reservation dashboard with booking totals and tonight's service overview",
        },
        {
          src: "/screenshots/tbms/admin-reservations.png",
          alt: "Restaurant admin — reservation management with guest, party, table and status detail",
        },
        {
          src: "/screenshots/tbms/admin-tables.png",
          alt: "Restaurant admin — table management with floor status and booking-availability controls",
        },
        {
          src: "/screenshots/tbms/admin-customers.png",
          alt: "Restaurant admin — customer directory with guest booking history",
        },
      ],
    },
    projectUrl: "https://tbms.permetheon.com/",
  },
  {
    slug: "estatehub",
    name: "EstateHub",
    fullPositioning: "Real Estate Listing & Meeting Booking Platform",
    seoTitle: "EstateHub — Real Estate Listing Platform",
    headline: "Making property discovery more actionable.",
    description:
      "EstateHub provides a digital environment for browsing real-estate listings and moving from property discovery toward scheduled meetings.",
    metadata: ["Real Estate", "Property Platform", "Listing System", "Meeting Booking"],
    flow: ["Discover Property", "View Listing", "Explore Details", "Schedule Meeting"],
    sections: [
      {
        heading: "The Real Estate Experience",
        body: "EstateHub is designed around one journey: from discovering a property to taking action on it.",
      },
      {
        heading: "Property Listings",
        body: "Listings are presented so buyers can evaluate properties quickly and clearly.",
      },
      {
        heading: "Property Discovery",
        body: "Discovery is built around browsing properties by category and exploring what's available.",
      },
      {
        heading: "Property Details",
        body: "Each property has a dedicated detail view for exploring the listing in depth.",
        assetLabel: "EstateHub — property detail screenshots pending (B-002)",
      },
      {
        heading: "Meeting Scheduling",
        body: "From property details, the journey moves toward scheduling a meeting — making discovery actionable.",
        assetLabel: "EstateHub — scheduling screenshots pending (B-002)",
      },
      {
        heading: "UX / UI",
        body: "A property-focused experience: clear listing presentation, fast discovery, and a direct path from interest to action.",
      },
      {
        heading: "Technology",
        body: "Custom Web Application for property listings and meeting booking.",
      },
    ],
    showcase: {
      images: [
        // Journey order — discover (home + search) → browse listings → filter → platform management → site chrome.
        {
          src: "/screenshots/estatehub/hero-search.png",
          alt: "EstateHub home — “Find a place you'll love to live — or work.” with location, type and price search",
        },
        {
          src: "/screenshots/estatehub/featured-listings.png",
          alt: "Featured listings — property cards with price, beds, baths, size and agent contact",
        },
        {
          src: "/screenshots/estatehub/properties-search.png",
          alt: "All Properties — listing grid with search and category/price filters",
        },
        {
          src: "/screenshots/estatehub/admin-dashboard.png",
          alt: "Admin dashboard — platform stats, listing approvals and oversight",
        },
        {
          src: "/screenshots/estatehub/admin-inquiries.png",
          alt: "Admin inquiries — buyer messages with property context in the management view",
        },
        {
          src: "/screenshots/estatehub/footer.png",
          alt: "Site footer — platform navigation, contact details and Permetheon attribution",
        },
      ],
    },
    projectUrl: "https://estatehub.permetheon.com/",
  },
  {
    slug: "travelnest",
    name: "TravelNest",
    fullPositioning: "Travel Discovery & Trip Planning Platform",
    seoTitle: "TravelNest — Travel Discovery & Planning Platform",
    headline: "A digital journey from discovering a destination to planning the trip.",
    description:
      "TravelNest brings travel discovery, destinations, tour packages and planning tools together into one experience.",
    metadata: ["Travel", "Digital Experience", "Tour Platform", "Trip Planning"],
    flow: ["Discover", "Search", "Explore", "Choose", "Plan"],
    sections: [
      {
        heading: "Destination Discovery",
        body: "TravelNest opens with destination discovery — an engaging entry point into the travel experience.",
      },
      {
        heading: "Travel Search",
        body: "Search lets travelers shape their journey: destination, dates, guests and budget.",
      },
      {
        heading: "Tour Packages",
        body: "Curated tour packages present complete travel experiences.",
      },
      {
        heading: "Adventure Categories & Featured Destinations",
        body: "Categories and featured destinations guide travelers from inspiration to selection.",
      },
      {
        heading: "Traveler Stories & Gallery",
        body: "Traveler stories and a travel gallery carry the experience from browsing to planning.",
      },
      {
        heading: "UX / UI",
        body: "An immersive travel experience that keeps discovery engaging while keeping the path to planning clear.",
      },
      {
        heading: "Technology",
        body: "Custom Web Application for travel discovery and trip planning.",
      },
    ],
    showcase: {
      images: [
        // Journey order — discover (home + search) → explore (categories, destinations)
        // → choose (packages) → trust (why panels) → stories → site chrome.
        {
          src: "/screenshots/travelnest/hero-search.png",
          alt: "TravelNest home — “Discover Your Next Adventure” hero with destination, date, guests and budget search",
        },
        {
          src: "/screenshots/travelnest/adventure-categories.png",
          alt: "Adventure Categories — Beach, Mountains, Luxury, Honeymoon, Family, Wildlife, Cruise and Adventure tours",
        },
        {
          src: "/screenshots/travelnest/popular-destinations.png",
          alt: "Popular Destinations — featured destination cards for Kyoto, Amalfi Coast and North Malé Atoll",
        },
        {
          src: "/screenshots/travelnest/tour-packages.png",
          alt: "Featured Tour Packages — itinerary cards with durations, highlights and package details",
        },
        {
          src: "/screenshots/travelnest/why-travelnest.png",
          alt: "Why TravelNest — trust panels covering secure booking, expert guides, best price guarantee and personalized trips",
        },
        {
          src: "/screenshots/travelnest/traveler-stories.png",
          alt: "Traveler Stories — testimonial carousel with reviews from TravelNest travelers",
        },
        {
          src: "/screenshots/travelnest/footer.png",
          alt: "Site footer — navigation, contact details, popular destinations and support links",
        },
      ],
    },
    projectUrl: "https://travelnest.permetheon.com/",
  },
];
