
export async function api(url, options = {}) {
  const response = await fetch(url, {
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });
  const body = await response.json();
  if (!response.ok) {
    const details = body.errors ? ' ' + Object.values(body.errors).flat().join(' ') : '';
    const err = new Error((body.error || 'Error de API') + details);
    err.errors = body.errors;
    throw err;
  }
  return body;
}
