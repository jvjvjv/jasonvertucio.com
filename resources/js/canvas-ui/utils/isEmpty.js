/**
 * Check if a value is empty (null, undefined, empty array, or empty object)
 * @param {*} value - The value to check
 * @returns {boolean} - True if value is empty
 */
export function isEmpty(value) {
    if (value == null) return true;
    if (Array.isArray(value)) return value.length === 0;
    if (typeof value === 'object') return Object.keys(value).length === 0;
    return false;
}
