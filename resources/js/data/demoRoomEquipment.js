/**
 * Fallback mock data when the schedule page has no rooms/equipment from the API.
 * Matches database/seeders/DemoRoomSeeder.php
 */
export const DEMO_ROOM_EQUIPMENTS = {
    'UG 114': ['Chairs', 'Table', 'Remote Control'],
    'AVR 201': ['Chairs', 'Projector', 'Air Conditioner', 'Microphone'],
    'Lab 305': ['Chairs', 'Table', 'Desktop Computer', 'Printer'],
    'Main Conference Room': ['Chairs', 'Table', 'Projector', 'Whiteboard', 'Speaker System'],
};

export const DEMO_ROOM_EQUIPMENT_QUANTITIES = {
    'UG 114': {
        chairs: 10,
        table: 4,
        'remote control': 2,
    },
    'AVR 201': {
        chairs: 30,
        projector: 1,
        'air conditioner': 2,
        microphone: 4,
    },
    'Lab 305': {
        chairs: 20,
        table: 8,
        'desktop computer': 12,
        printer: 2,
    },
    'Main Conference Room': {
        chairs: 40,
        table: 6,
        projector: 1,
        whiteboard: 2,
        'speaker system': 1,
    },
};

const withLowercaseAliases = (source) => {
    const map = { ...source };
    Object.entries(source).forEach(([key, value]) => {
        map[String(key).toLowerCase()] = value;
    });
    return map;
};

/** Global inventory totals for offline fallback (COUNT per catalog name, same as equipment picker). */
export const GLOBAL_DEMO_EQUIPMENT_QUANTITIES = {
    chairs: 5,
    table: 4,
    'remote control': 2,
    clock: 1,
    projector: 1,
    'air conditioner': 2,
    microphone: 4,
    'desktop computer': 12,
    printer: 2,
    whiteboard: 2,
    'speaker system': 1,
    tv: 3,
    television: 2,
};

export const demoRoomEquipmentsMap = withLowercaseAliases(DEMO_ROOM_EQUIPMENTS);

export const demoRoomEquipmentQuantitiesMap = withLowercaseAliases(DEMO_ROOM_EQUIPMENT_QUANTITIES);

/** [{ name, quantity }] per room for the appointment modal */
export const demoRoomEquipmentDetailsMap = (() => {
    const map = {};
    Object.entries(DEMO_ROOM_EQUIPMENTS).forEach(([room, names]) => {
        const quantities = DEMO_ROOM_EQUIPMENT_QUANTITIES[room] || {};
        const details = names.map((name) => ({
            name,
            quantity: quantities[String(name).toLowerCase()] ?? 0,
        }));
        map[room] = details;
        map[String(room).toLowerCase()] = details;
    });
    return map;
})();
