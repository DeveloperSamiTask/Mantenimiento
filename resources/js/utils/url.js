export function reloadWithQuery(newParams) {
  const url = new URL(window.location.href);
  const params = new URLSearchParams(url.search);


  for (const key in newParams) {
    const value = newParams[key];

    // LIMPIAR RADICALMENTE - eliminar TODOS los parámetros que coincidan
    const allKeys = Array.from(params.keys());
    allKeys.forEach(paramKey => {
      // Eliminar dateRange, dateRange[], dateRange[0], dateRange[1], etc.
      if (paramKey.startsWith(key) || paramKey.startsWith(`${key}[`)) {
        params.delete(paramKey);
      }
    });

    if (value !== null && value !== undefined && value !== '') {
      if (Array.isArray(value)) {
        value.forEach(val => {
          if (val !== null && val !== undefined && val !== '') {
            params.append(`${key}[]`, val);
          }
        });
      } else {
        params.set(key, value);
      }
    }
  }


  import('@inertiajs/react').then(({ router }) => {
    router.get(window.location.pathname + '?' + params.toString(), {}, {
      preserveState: true,
      replace: true,
    });
  });
}

export function currentUrlParams() {
  const params = new URLSearchParams(window.location.search);
  const result = {};
  for (const [key, value] of params.entries()) {
    if (key.endsWith('[]')) {
      const cleanKey = key.replace('[]', '');
      result[cleanKey] = result[cleanKey] || [];
      result[cleanKey].push(value);
    } else {
      result[key] = value;
    }
  }
  return result;
}
