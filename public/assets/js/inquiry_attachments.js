const InquiryAttachments = (() => {
  const maxFiles = 5;
  const maxSize = 5 * 1024 * 1024;

  function sizeLabel(bytes) {
    return bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.ceil(bytes / 1024))} KB`;
  }

  function icon(name) {
    const image = document.createElement('img');
    image.src = `assets/icons/${name}.svg`;
    image.alt = '';
    image.width = 16;
    image.height = 16;
    return image;
  }

  function showError(form, text) {
    const alert = form.querySelector('[data-error-msg]');
    alert.textContent = text;
    alert.style.display = 'block';
  }

  function renderSelection(form) {
    const state = form.escalationAttachments;
    const list = form.querySelector('.esc-file-list');
    list.replaceChildren();
    state.items.forEach(item => {
      const row = document.createElement('div');
      row.className = 'esc-file-item';
      const name = document.createElement('span');
      name.className = 'esc-file-name';
      name.textContent = item.file.name;
      name.title = item.file.name;
      const details = document.createElement('span');
      details.className = 'esc-file-size';
      details.textContent = `${sizeLabel(item.file.size)} - ${item.status || 'Selected'}`;
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'esc-file-remove';
      remove.title = `Remove ${item.file.name}`;
      remove.setAttribute('aria-label', remove.title);
      remove.disabled = state.busy;
      remove.append(icon('x'));
      remove.addEventListener('click', () => {
        state.items = state.items.filter(selected => selected !== item);
        renderSelection(form);
      });
      row.append(name, details, remove);
      list.append(row);
    });
  }

  function bindForm(form) {
    if (form.escalationAttachments) return;
    form.escalationAttachments = { items: [], busy: false };
    const input = form.elements.namedItem('attachment');
    const button = form.querySelector('.esc-file-btn');
    button.removeAttribute('onclick');
    button.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
      const state = form.escalationAttachments;
      const files = Array.from(input.files || []);
      input.value = '';
      if (state.busy || files.length === 0) return;
      if (state.items.length + files.length > maxFiles) {
        showError(form, 'Attach up to five files.');
        return;
      }
      for (const file of files) {
        if (file.size === 0 || file.size > maxSize) {
          showError(form, `${file.name}: choose a non-empty file up to 5 MB.`);
          return;
        }
        if (!/\.(jpe?g|png|gif|webp|pdf|docx?)$/i.test(file.name)) {
          showError(form, `${file.name}: choose a JPG, PNG, GIF, WebP, PDF, or Word file.`);
          return;
        }
      }
      state.items.push(...files.map(file => ({ file, attachmentId: null })));
      form.querySelector('[data-error-msg]').style.display = 'none';
      renderSelection(form);
    });
  }

  function setBusy(form, busy) {
    if (!form.escalationAttachments) return;
    form.escalationAttachments.busy = busy;
    form.elements.namedItem('attachment').disabled = busy;
    form.querySelector('.esc-file-btn').disabled = busy;
    renderSelection(form);
  }

  async function upload(form, csrfToken, onProgress) {
    const items = form.escalationAttachments?.items || [];
    for (let index = 0; index < items.length; index++) {
      const item = items[index];
      if (item.attachmentId) continue;
      onProgress(`Uploading file ${index + 1} of ${items.length}...`);
      item.status = 'Uploading';
      renderSelection(form);
      try {
        const payload = new FormData();
        payload.append('file', item.file);
        const response = await fetch('api/upload_file.php', {
          method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: payload,
        });
        const result = await response.json();
        if (!response.ok || !result.success || !Number.isSafeInteger(result.attachment_id) || result.attachment_id < 1) {
          throw new Error(result.error || 'File upload failed. Please try again.');
        }
        item.attachmentId = result.attachment_id;
        item.status = 'Ready';
      } catch (error) {
        item.status = 'Upload failed';
        throw new Error(`${item.file.name}: ${error.message || 'File upload failed. Please try again.'}`);
      } finally {
        renderSelection(form);
      }
    }
    return items.map(item => item.attachmentId);
  }

  function create(attachments = []) {
    const list = document.createElement('ul');
    list.className = 'inquiry-attachments';
    list.setAttribute('aria-label', 'Attached files');
    for (const attachment of attachments) {
      const id = Number(attachment.attachment_id);
      if (!Number.isSafeInteger(id) || id < 1) continue;
      const row = document.createElement('li');
      const link = document.createElement('a');
      link.href = `api/download_attachment.php?id=${id}`;
      link.title = `Download ${attachment.file_name}`;
      const name = document.createElement('span');
      name.textContent = attachment.file_name;
      const size = document.createElement('small');
      size.textContent = sizeLabel(Number(attachment.file_size) || 0);
      link.append(icon('download'), name, size);
      row.append(link);
      list.append(row);
    }
    return list;
  }

  function selected(form) {
    return (form.escalationAttachments?.items || []).map(item => ({
      attachment_id: item.attachmentId, file_name: item.file.name, file_size: item.file.size,
    }));
  }

  return { bindForm, setBusy, upload, create, selected };
})();
