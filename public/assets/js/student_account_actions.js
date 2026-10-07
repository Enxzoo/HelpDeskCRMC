'use strict';
function confirmImportantAction({ title, message, confirmLabel, destructive = false }) {
  const dialog = document.getElementById('actionConfirmDialog');
  const cancelButton = document.getElementById('actionConfirmCancel');
  const continueButton = document.getElementById('actionConfirmContinue');
  document.getElementById('actionConfirmTitle').textContent = title;
  document.getElementById('actionConfirmMessage').textContent = message;
  continueButton.textContent = confirmLabel;
  continueButton.classList.toggle('destructive', destructive);
  dialog.returnValue = 'cancel';
  cancelButton.onclick = () => dialog.close('cancel');
  continueButton.onclick = () => dialog.close('confirm');
  dialog.onclick = event => {
    if (event.target === dialog) dialog.close('cancel');
  };

  return new Promise(resolve => {
    dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true });
    dialog.showModal();
    cancelButton.focus();
  });
}

// Handle logout links
document.querySelectorAll('.student-logout-link').forEach(link => {
  link.addEventListener('click', async event => {
    if (link.dataset.confirmingLogout === 'true') return;
    event.preventDefault();
    link.dataset.confirmingLogout = 'true';
    const confirmed = await confirmImportantAction({
      title: 'Log out?',
      message: 'Are you sure you want to log out of your account?',
      confirmLabel: 'Log out',
      destructive: true
    });
    link.dataset.confirmingLogout = 'false';
    if (confirmed) window.location.assign(link.href);
  });
});

// Handle logout forms
document.querySelectorAll('#logoutForm, .logout-form').forEach(form => {
  form.addEventListener('submit', async event => {
    if (form.dataset.confirmingLogout === 'true') return;
    event.preventDefault();
    form.dataset.confirmingLogout = 'true';
    const confirmed = await confirmImportantAction({
      title: 'Log out?',
      message: 'Are you sure you want to log out of your account?',
      confirmLabel: 'Log out',
      destructive: true
    });
    form.dataset.confirmingLogout = 'false';
    if (confirmed) form.requestSubmit();
  });
});

