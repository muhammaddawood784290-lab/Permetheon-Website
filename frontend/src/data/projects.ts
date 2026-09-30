/**
 * PERMETHEON — PROJECT DATA (single source for homepage, Work, case studies)
 * Per docs/06_PROJECT_DATA_MODEL.md — structured data, not hardcoded components.
 * Every field is VERIFIED — Master Document (docs/DESIGN.md Secs. 04, 06, 08, 25)
 * or VERIFIED — Project Source (live checks recorded in docs/07_PROJECT_CONTENT.md).
 * Technologies are intentionally EMPTY — **D-009 RESOLVED (2026-09-21)**: generic
 * "Custom Web Application" wording authorized as final; stacks only if later verified.
 */

export type Project = {
  id: string;
  name: string;
  fullPositioning: string;
  category: string;
  /** Industry label — derived from the project's first verified metadata tag (Master Sec. 06/07). */
  industry: string;
  shortDescription: string;
  projectUrl: string;
  caseStudyUrl: string;
  capabilities: string[];
  metadata: string[];
  /**
   * Work-page filter assignments. PROPOSED mapping pending D-003 approval
   * (docs/09 flag): derived only from verified Master categories.
   * Changing a project's filters = data-only edit; components never change.
   */
  filters: string[];
  featuredImageLabel: string;
  /** Real owner-provided screenshot (B-002), shown in project cards/featured slots when present. */
  previewImage?: { src: string; alt: string };
  caseStudyHeadline: string;
};

export const PROJECTS: Project[] = [
  {
    id: "pct",
    name: "PCT",
    fullPositioning: "Permetheon Command Terminal",
    category: "Internal Business Platform",
    industry: "Internal Platform",
    shortDescription:
      "A centralized internal command terminal built to manage projects, teams, tasks and day-to-day digital operations across Permetheon's developers, designers and other teams.",
    projectUrl: "https://pct.permetheon.com/",
    caseStudyUrl: "/case-studies/pct",
    capabilities: [
      "Project management",
      "Team management",
      "Developer workflows",
      "Designer workflows",
      "Tasks",
      "Reviews",
      "Activity",
      "Notifications",
      "Reports",
      "User management",
      "Operational visibility",
    ],
    metadata: ["Internal Platform", "Project Management", "Team Operations", "Custom Software"],
    filters: ["Internal Platforms", "Business Systems"],
    featuredImageLabel: "PCT — dashboard screenshot",
    previewImage: {
      src: "/screenshots/pct/dashboard.png",
      alt: "PCT dashboard — workspace overview with active tasks, reviews, projects and quick actions",
    },
    caseStudyHeadline: "One command center for the teams building Permetheon.",
  },
  {
    id: "tbms",
    name: "TBMS",
    fullPositioning: "Restaurant Reservation & Table Management System",
    category: "Restaurant Reservation & Table Management",
    industry: "Restaurant",
    shortDescription:
      "TBMS brings restaurant reservations, table management and operational control into one connected digital system.",
    projectUrl: "https://tbms.permetheon.com/",
    caseStudyUrl: "/case-studies/tbms",
    capabilities: [
      "Restaurant table management",
      "Reservation / booking",
      "Customer booking portal",
      "Admin portal",
      "Reservation management",
      "Table availability",
      "Operational management",
    ],
    metadata: ["Restaurant", "Reservation System", "Booking Portal", "Admin Platform"],
    filters: ["Booking Systems"],
    featuredImageLabel: "TBMS — booking portal screenshot",
    previewImage: {
      src: "/screenshots/tbms/customer-home.png",
      alt: "TBMS customer booking site — restaurant homepage with table reservation entry points",
    },
    caseStudyHeadline: "Turning restaurant reservations into a connected digital workflow.",
  },
  {
    id: "estatehub",
    name: "EstateHub",
    fullPositioning: "Real Estate Listing & Meeting Booking Platform",
    category: "Real Estate Platform",
    industry: "Real Estate",
    shortDescription:
      "EstateHub connects property listings with a streamlined experience for discovering properties and scheduling meetings.",
    projectUrl: "https://estatehub.permetheon.com/",
    caseStudyUrl: "/case-studies/estatehub",
    capabilities: ["Property Listings", "Property Discovery", "Property Details", "Meeting Booking"],
    metadata: ["Real Estate", "Property Platform", "Listing System", "Meeting Booking"],
    filters: ["Websites", "Booking Systems"],
    featuredImageLabel: "EstateHub — listing view screenshot",
    previewImage: {
      src: "/screenshots/estatehub/hero-search.png",
      alt: "EstateHub home — property search with location, type and price filters",
    },
    caseStudyHeadline: "Making property discovery more actionable.",
  },
  {
    id: "travelnest",
    name: "TravelNest",
    fullPositioning: "Travel Discovery & Trip Planning Platform",
    category: "Travel Platform",
    industry: "Travel",
    shortDescription:
      "TravelNest brings destination discovery, tour packages and trip planning into one engaging travel experience.",
    projectUrl: "https://travelnest.permetheon.com/",
    caseStudyUrl: "/case-studies/travelnest",
    capabilities: [
      "Destination discovery",
      "Travel search",
      "Destination selection",
      "Date selection",
      "Guest selection",
      "Budget selection",
      "Adventure categories",
      "Featured destinations",
      "Tour packages",
      "Traveler stories",
      "Travel gallery",
    ],
    metadata: ["Travel", "Digital Experience", "Tour Platform", "Trip Planning"],
    filters: ["Websites"],
    featuredImageLabel: "TravelNest — homepage screenshot",
    previewImage: {
      src: "/screenshots/travelnest/hero-search.png",
      alt: "TravelNest — homepage with destination discovery hero and travel search",
    },
    caseStudyHeadline: "A digital journey from discovering a destination to planning the trip.",
  },
];

/** Homepage capability strip (Master Sec. 05 — verified list). */
export const CAPABILITY_TAGS = [
  "Websites",
  "Web Applications",
  "Business Platforms",
  "Booking Systems",
  "Management Systems",
  "Portals",
  "Custom Software",
] as const;

/** Work-page filter categories (Master Sec. 07 / Phase 04 — verified list, fixed order). */
export const FILTER_CATEGORIES = [
  "All",
  "Websites",
  "Web Applications",
  "Business Systems",
  "Booking Systems",
  "Portals",
  "Internal Platforms",
] as const;

/** Services (Master Sec. 09 — verified capability lists + proof links). */
export const SERVICES = [
  {
    id: "website-development",
    name: "Website Development",
    capabilities: ["Business websites", "Corporate websites", "Landing pages", "E-commerce websites", "Website redesigns"],
    relatedProjects: [] as string[],
  },
  {
    id: "web-applications",
    name: "Web Applications",
    capabilities: ["Custom web applications", "Customer portals", "Admin platforms", "Booking platforms", "Management applications"],
    relatedProjects: [] as string[],
  },
  {
    id: "business-systems",
    name: "Business Systems",
    capabilities: ["Management systems", "Internal platforms", "CRM", "Project systems", "Operations software", "Workflow systems"],
    relatedProjects: ["pct"] as string[],
  },
  {
    id: "booking-systems",
    name: "Booking & Reservation Systems",
    capabilities: ["Restaurant reservations", "Appointment booking", "Meeting scheduling", "Availability systems", "Customer booking portals"],
    relatedProjects: ["tbms", "estatehub"] as string[],
  },
  {
    id: "ui-ux-design",
    name: "UI/UX & Product Design",
    capabilities: ["Research", "User flows", "Wireframes", "Figma", "Design systems", "Responsive interfaces", "Product UX"],
    relatedProjects: [] as string[],
  },
  {
    id: "custom-digital-products",
    name: "Custom Digital Products",
    capabilities: ["A solution designed around your specific business idea, process or problem"],
    relatedProjects: [] as string[],
  },
] as const;

/** Process stages (Master Sec. 11 — verified definitions; expanded copy per docs/20, owner approval pending for the page). */
export const PROCESS_STAGES = [
  { number: "01", name: "Discover", summary: "Understand the business, users and problem." },
  { number: "02", name: "Define", summary: "Turn requirements into a clear scope and roadmap." },
  { number: "03", name: "Design", summary: "Create the product experience and interface." },
  { number: "04", name: "Develop", summary: "Build the actual product." },
  { number: "05", name: "Test", summary: "Validate functionality, responsiveness and reliability." },
  { number: "06", name: "Deploy", summary: "Launch the product into the real world." },
] as const;

/**
 * PROCESS STAGE DETAILS (docs/20 draft — derived from the Master's six verified
 * stage definitions, zero factual claims). Owner authorized building the Process
 * page from this draft; formal copy approval (B-007) is still pending — merge
 * into the verified baseline only on approval.
 */
export const PROCESS_STAGE_DETAILS: Record<
  string,
  { activities: string[]; deliverables: string[]; involvement: string[] }
> = {
  Discover: {
    activities: [
      "Learn how your business operates, who your customers are and what the product must achieve",
      "Map the current process — what exists today, what's missing, what hurts",
      "Identify users, their goals and the constraints the product must respect",
    ],
    deliverables: [
      "A clear problem statement",
      "Documented business requirements and user goals",
      "Shared understanding of scope direction before anything is designed",
    ],
    involvement: [
      "A discovery conversation about your business, customers and goals",
      "Answers to practical questions about how you work today",
      "Review of the problem statement before we move on",
    ],
  },
  Define: {
    activities: [
      "Translate discovery findings into specific, prioritized requirements",
      "Decide what the product does first — and what waits for a later iteration",
      "Shape the scope and roadmap that development will follow",
    ],
    deliverables: [
      "A defined scope with priorities",
      "A product roadmap",
      "Agreement on what “done” looks like for this phase of work",
    ],
    involvement: [
      "Approve the scope and priorities",
      "Flag anything that's missing or mis-weighted before it becomes a plan",
      "Confirm the roadmap matches your business timeline",
    ],
  },
  Design: {
    activities: [
      "Structure the product: user flows, screens and interactions",
      "Design the interface around real content and real workflows",
      "Apply the design system so the product feels coherent and premium",
    ],
    deliverables: [
      "The designed product experience — flows, screens and interface",
      "A design you can review screen by screen before development begins",
    ],
    involvement: [
      "Review designs against how your business actually works",
      "Give focused feedback at defined checkpoints",
      "Approve the design before build",
    ],
  },
  Develop: {
    activities: [
      "Implement the approved design as a working product",
      "Build on a foundation that can grow with the business",
      "Keep design, content and functionality aligned as the product takes shape",
    ],
    deliverables: [
      "A working product you can open and use",
      "Regular, visible progress — not a surprise reveal at the end",
    ],
    involvement: [
      "See the product as it develops",
      "Provide real content and materials when needed",
      "Confirm behaviors match the business reality",
    ],
  },
  Test: {
    activities: [
      "Test the product across desktop, tablet and mobile",
      "Verify forms, navigation, interactions and edge cases",
      "Fix and re-check until the product behaves reliably",
    ],
    deliverables: [
      "A product that works on the devices your customers actually use",
      "Responsive and functional QA completed and documented",
    ],
    involvement: [
      "Try the product yourself — your practical review matters as much as ours",
      "Report anything that doesn't behave the way your business expects",
    ],
  },
  Deploy: {
    activities: [
      "Prepare and validate the production environment",
      "Launch the product",
      "Make sure everything works where it counts: live",
    ],
    deliverables: [
      "A live, production-ready product",
      "A working launch — configured domain, secure connection, verified functionality",
    ],
    involvement: [
      "Final approval to go live",
      "Access or credentials needed for production (domain, hosting)",
    ],
  },
};

/** Core principles (Master Sec. 13 — verified titles only; no invented descriptions). */
export const CORE_PRINCIPLES = [
  "Business First",
  "Purposeful Design",
  "Solid Engineering",
  "Continuous Improvement",
] as const;

/** Contact form project types (Master Sec. 16 — verified list). */
export const PROJECT_TYPES = [
  "Website",
  "Web Application",
  "Business System",
  "Booking System",
  "Portal",
  "E-commerce",
  "Custom Software",
  "Other",
] as const;

/** Why Permetheon (Master Sec. 12 — verified copy). */
export const WHY_PERMETHEON = [
  { title: "Purpose-built", body: "No unnecessary features. No generic templates." },
  { title: "Design + Development", body: "Product design and engineering working together." },
  { title: "Business-focused", body: "Technology exists to solve a real business problem." },
  { title: "Flexible", body: "From a single website to a complete business platform." },
  { title: "Scalable", body: "Build foundations that can evolve with the business." },
  { title: "Long-term", body: "We think beyond launch." },
] as const;

/** The Difference sequence (Master Sec. 10 — verified). */
export const DIFFERENCE_SEQUENCE = [
  "Business",
  "Problem",
  "Requirements",
  "Product Strategy",
  "UX / UI",
  "Development",
  "Testing",
  "Deployment",
] as const;
