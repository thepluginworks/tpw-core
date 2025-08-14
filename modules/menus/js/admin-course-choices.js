document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.rename-trigger').forEach(button => {
    button.addEventListener('click', e => {
      const heading = e.target.closest('h2');
      heading.style.display = 'none';
      heading.nextElementSibling.style.display = 'block';
    });
  });

  document.querySelectorAll('.cancel-rename').forEach(button => {
    button.addEventListener('click', e => {
      const form = e.target.closest('form');
      form.style.display = 'none';
      form.previousElementSibling.style.display = 'block';
    });
  });
});