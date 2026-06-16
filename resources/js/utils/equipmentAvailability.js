/**
 * User-facing label for equipment quantity (room booking & equipment picker).
 */
export function formatEquipmentAvailability(quantity) {
    if (quantity === null || quantity === undefined || Number.isNaN(Number(quantity))) {
        return 'Not available';
    }

    return `${Number(quantity)} available`;
}
