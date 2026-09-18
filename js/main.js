// Placeholder links explain their status until publication URLs are supplied.
// To activate a link, replace its href and remove its data-placeholder attribute.
const linkStatus = document.querySelector('#link-status');

document.querySelectorAll('a[data-placeholder]').forEach((link) => {
  link.addEventListener('click', (event) => {
    if (!linkStatus) return;
    event.preventDefault();
    linkStatus.textContent = `${link.dataset.placeholder}: the publication link has not been added yet.`;
  });
});
