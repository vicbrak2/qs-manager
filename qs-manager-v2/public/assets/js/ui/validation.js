
export function clearFormErrors(form) {
  form.querySelectorAll('.invalid-field').forEach(el => el.classList.remove('invalid-field'));
  form.querySelectorAll('.error-helper-text').forEach(el => el.remove());
}

export function showFormErrors(form, errors) {
  clearFormErrors(form);
  if (!errors) return;
  Object.keys(errors).forEach((field) => {
    const input = form.querySelector(`[name="${field}"]`);
    if (input) {
      input.classList.add('invalid-field');
      const helper = document.createElement('span');
      helper.className = 'error-helper-text';
      const msg = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
      helper.textContent = msg || '';
      input.parentNode.appendChild(helper);
    }
  });
}
