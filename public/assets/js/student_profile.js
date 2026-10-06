'use strict';
(() => {
  const college = document.getElementById('college_id');
  const program = document.getElementById('program_id');
  const year = document.getElementById('year_level');
  function filterPrograms() {
    for (const option of program.options) {
      if (!option.value) continue;
      option.hidden = option.disabled = option.dataset.college !== college.value;
    }
    if (program.selectedOptions[0]?.disabled) program.value = '';
    filterYears();
  }
  function filterYears() {
    const years = Number(program.selectedOptions[0]?.dataset.years || 0);
    for (const option of year.options) {
      if (option.value) option.hidden = option.disabled = Number(option.value) > years;
    }
    if (year.selectedOptions[0]?.disabled) year.value = '';
  }
  if (college && program && year) {
    college.addEventListener('change', filterPrograms);
    program.addEventListener('change', filterYears);
    filterPrograms();
  }
  document.querySelectorAll('[data-password-toggle]').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.passwordToggle);
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      button.title = show ? 'Hide password' : 'Show password';
    });
  });
  const registrationForm = document.getElementById('studentRegistration');
  if (registrationForm) {
    const steps = [...registrationForm.querySelectorAll('[data-registration-step]')];
    const progress = document.querySelector('.registration-progress');
    const progressItems = progress ? [...progress.querySelectorAll('[data-registration-progress]')] : [];
    const back = registrationForm.querySelector('[data-registration-back]');
    const next = registrationForm.querySelector('[data-registration-next]');
    const submit = registrationForm.querySelector('[data-registration-submit]');
    const password = registrationForm.elements.password;
    const confirmation = registrationForm.elements.confirm_password;

    if (steps.length === 3 && progressItems.length === steps.length && back && next && submit) {
      const canSubmit = !submit.disabled;
      let currentStep = Math.min(Math.max(Number(registrationForm.dataset.initialStep) || 0, 0), steps.length - 1);

      const showStep = (index, focus = false) => {
        currentStep = index;
        steps.forEach((step, stepIndex) => { step.hidden = stepIndex !== index; });
        progressItems.forEach((item, stepIndex) => {
          item.classList.toggle('is-current', stepIndex === index);
          item.classList.toggle('is-complete', stepIndex < index);
          if (stepIndex === index) item.setAttribute('aria-current', 'step');
          else item.removeAttribute('aria-current');
        });
        back.hidden = index === 0;
        next.hidden = index === steps.length - 1;
        submit.hidden = index !== steps.length - 1;

        if (focus) {
          const legend = steps[index].querySelector('legend');
          if (legend) {
            legend.tabIndex = -1;
            legend.focus();
          }
        }
      };

      const updatePasswordValidity = () => {
        confirmation.setCustomValidity(
          confirmation.value && confirmation.value !== password.value ? 'Passwords do not match.' : ''
        );
      };

      const validateStep = index => {
        if (index === steps.length - 1) updatePasswordValidity();
        const controls = steps[index].querySelectorAll('input:not([type="hidden"]), select, textarea');
        for (const control of controls) {
          if (!control.checkValidity()) {
            showStep(index);
            control.reportValidity();
            return false;
          }
        }
        return true;
      };

      registrationForm.noValidate = true;
      progress.hidden = false;
      showStep(currentStep);

      next.addEventListener('click', () => {
        if (validateStep(currentStep)) showStep(currentStep + 1, true);
      });
      back.addEventListener('click', () => showStep(currentStep - 1, true));
      password.addEventListener('input', updatePasswordValidity);
      confirmation.addEventListener('input', updatePasswordValidity);

      registrationForm.addEventListener('submit', event => {
        if (!canSubmit) {
          event.preventDefault();
          return;
        }
        for (let index = 0; index < steps.length; index++) {
          if (!validateStep(index)) {
            event.preventDefault();
            return;
          }
        }
        registrationForm.setAttribute('aria-busy', 'true');
        submit.disabled = true;
        submit.querySelector('span').textContent = 'Creating account...';
      });

      window.addEventListener('pageshow', () => {
        registrationForm.removeAttribute('aria-busy');
        submit.disabled = !canSubmit;
        submit.querySelector('span').textContent = 'Create account';
      });
    }
  }
  document.querySelectorAll('[data-profile-contact]').forEach(form => {
    const mobile = form.elements.mobile_number;
    const save = form.querySelector('[data-contact-save]');
    const reset = form.querySelector('[data-contact-reset]');
    const original = form.dataset.originalMobile || '';
    const saveLabel = save.querySelector('span');
    const defaultLabel = saveLabel.textContent;
    const update = () => {
      const changed = mobile.value.trim() !== original.trim();
      save.disabled = form.dataset.saving === 'true' || !changed;
      reset.hidden = !changed;
    };
    mobile.addEventListener('input', update);
    reset.addEventListener('click', () => {
      mobile.value = original;
      update();
      mobile.focus();
    });
    form.addEventListener('submit', event => {
      if (form.dataset.saving === 'true' || mobile.value.trim() === original.trim()) { event.preventDefault(); return; }
      form.dataset.saving = 'true';
      form.setAttribute('aria-busy', 'true');
      save.disabled = true;
      reset.disabled = true;
      saveLabel.textContent = 'Saving...';
    });
    window.addEventListener('pageshow', () => {
      delete form.dataset.saving;
      form.removeAttribute('aria-busy');
      reset.disabled = false;
      saveLabel.textContent = defaultLabel;
      update();
    });
    update();
  });
})();
