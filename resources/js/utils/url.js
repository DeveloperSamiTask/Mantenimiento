// resources/js/utils/url.js

export function currentUrlParams() {
  const params = new URLSearchParams(window.location.search);
  const result = {};

  for (const [key, value] of params.entries()) {
    // Si es array (param[]=x&param[]=y), guardarlo como array
    if (key.endsWith('[]')) {
      const cleanKey = key.slice(0, -2);
      if (!result[cleanKey]) result[cleanKey] = [];
      result[cleanKey].push(value);
    } else {
      result[key] = value;
    }
  }

  return result;
}

export function reloadWithQuery(newParams) {
  const url = new URL(window.location.href);
  const params = new URLSearchParams(url.search);

  for (const key in newParams) {
    const value = newParams[key];

    if (value === null || value === undefined || value === '') {
      params.delete(key);
    } else if (Array.isArray(value)) {
      params.delete(key);
      value.forEach((val) => {
        params.append(`${key}[]`, val);
      });
    } else {
      params.set(key, value);
    }
  }

  window.location.search = params.toString();
}
