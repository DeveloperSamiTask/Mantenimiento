export function serializeFilters(filters) {
  const params = {};

  Object.entries(filters).forEach(([key, value]) => {
    if (value === null || value === undefined) return;

    // Si es array, solo agrega si tiene elementos
    if (Array.isArray(value)) {
      if (value.length > 0) {
        params[key] = value.join(',');
      }
    } else if (value !== '') {
      params[key] = value;
    }
  });

  return params;
}
