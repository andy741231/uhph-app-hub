document.addEventListener('DOMContentLoaded', () => {
  const buttons = document.querySelectorAll('.zoom-button');

  buttons.forEach((button) => {
    // Prevent default navigation for demo purposes.
    button.addEventListener('click', (event) => {
      event.preventDefault();
      console.log(`Clicked: ${button.dataset.label}`);
    });
  });
});
