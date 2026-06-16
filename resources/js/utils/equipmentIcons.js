import {
    faBox,
    faChair,
    faTv,
    faSnowflake,
    faFan,
    faVideo,
    faChalkboard,
    faMicrophone,
    faVolumeHigh,
    faLaptop,
    faDisplay,
    faPrint,
    faCamera,
    faTabletScreenButton,
    faWifi,
    faNetworkWired,
    faServer,
    faTable,
    faClock,
    faKeyboard,
    faComputerMouse,
    faMobileScreenButton,
    faFileLines,
} from '@fortawesome/free-solid-svg-icons'

/** Exact catalog name (lowercase) → Font Awesome icon */
const EXACT_ICONS = {
    tv: faTv,
    television: faTv,
    chairs: faChair,
    chair: faChair,
    'remote control': faMobileScreenButton,
    'air conditioner': faSnowflake,
    aircon: faSnowflake,
    projector: faVideo,
    whiteboard: faChalkboard,
    microphone: faMicrophone,
    'speaker system': faVolumeHigh,
    laptop: faLaptop,
    'desktop computer': faDisplay,
    printer: faPrint,
    scanner: faFileLines,
    camera: faCamera,
    tablet: faTabletScreenButton,
    router: faWifi,
    switch: faNetworkWired,
    server: faServer,
    desk: faTable,
    table: faTable,
    clock: faClock,
    fan: faFan,
    keyboard: faKeyboard,
    mouse: faComputerMouse,
}

/** Keyword fallback for factory-generated or custom equipment names */
const KEYWORD_RULES = [
    { pattern: /chair/i, icon: faChair },
    { pattern: /television|\btv\b/i, icon: faTv },
    { pattern: /remote/i, icon: faMobileScreenButton },
    { pattern: /aircon|air.?condition/i, icon: faSnowflake },
    { pattern: /projector/i, icon: faVideo },
    { pattern: /whiteboard|chalkboard/i, icon: faChalkboard },
    { pattern: /microphone|mic\b/i, icon: faMicrophone },
    { pattern: /speaker/i, icon: faVolumeHigh },
    { pattern: /laptop/i, icon: faLaptop },
    { pattern: /desktop|computer/i, icon: faDisplay },
    { pattern: /printer/i, icon: faPrint },
    { pattern: /scanner/i, icon: faFileLines },
    { pattern: /camera/i, icon: faCamera },
    { pattern: /tablet/i, icon: faTabletScreenButton },
    { pattern: /router/i, icon: faWifi },
    { pattern: /\bswitch\b/i, icon: faNetworkWired },
    { pattern: /server/i, icon: faServer },
    { pattern: /desk|table/i, icon: faTable },
    { pattern: /clock/i, icon: faClock },
    { pattern: /\bfan\b/i, icon: faFan },
    { pattern: /keyboard/i, icon: faKeyboard },
    { pattern: /mouse/i, icon: faComputerMouse },
]

/** Tailwind classes for icon avatar background (dropdown / pills) */
const STYLE_RULES = [
    { pattern: /chair/i, classes: 'bg-amber-100 text-amber-700' },
    { pattern: /television|\btv\b/i, classes: 'bg-indigo-100 text-indigo-700' },
    { pattern: /remote/i, classes: 'bg-slate-100 text-slate-600' },
    { pattern: /aircon|air.?condition/i, classes: 'bg-cyan-100 text-cyan-700' },
    { pattern: /projector/i, classes: 'bg-violet-100 text-violet-700' },
    { pattern: /whiteboard|chalkboard/i, classes: 'bg-emerald-100 text-emerald-700' },
    { pattern: /microphone|mic\b/i, classes: 'bg-rose-100 text-rose-700' },
    { pattern: /speaker/i, classes: 'bg-orange-100 text-orange-700' },
    { pattern: /laptop|desktop|computer|tablet|keyboard|mouse|server|router|switch/i, classes: 'bg-blue-100 text-blue-700' },
    { pattern: /printer|scanner/i, classes: 'bg-gray-100 text-gray-700' },
    { pattern: /camera/i, classes: 'bg-pink-100 text-pink-700' },
    { pattern: /desk|table/i, classes: 'bg-yellow-100 text-yellow-800' },
    { pattern: /clock/i, classes: 'bg-teal-100 text-teal-700' },
    { pattern: /\bfan\b/i, classes: 'bg-sky-100 text-sky-700' },
]

const DEFAULT_STYLE = 'bg-blue-100 text-blue-700'

export function getEquipmentIcon(name) {
    const key = String(name || '').trim().toLowerCase()
    if (EXACT_ICONS[key]) {
        return EXACT_ICONS[key]
    }

    for (const { pattern, icon } of KEYWORD_RULES) {
        if (pattern.test(name)) {
            return icon
        }
    }

    return faBox
}

export function getEquipmentIconStyle(name) {
    for (const { pattern, classes } of STYLE_RULES) {
        if (pattern.test(name)) {
            return classes
        }
    }
    return DEFAULT_STYLE
}
