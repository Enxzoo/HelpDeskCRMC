const BenChatUI = (() => {
  const labels = {
    en: { yes: 'Yes, thanks', no: 'Not yet', staff: 'Talk to staff',
      yesMessage: 'Yes, that answered my concern. Thank you!',
      noMessage: 'That did not answer my concern. Can you explain it another way?' },
    ceb: { yes: 'Oo, salamat', no: 'Wala pa', staff: 'Tabang sa staff',
      yesMessage: 'Oo, nasulbad na akong pangutana. Salamat!',
      noMessage: 'Wala pa masulbad akong pangutana. Pwede nimo ipasabot sa laing paagi?' },
    taglish: { yes: 'Oo, thanks', no: 'Hindi pa', staff: 'Talk to staff',
      yesMessage: 'Oo, nasagot na ang concern ko. Thank you!',
      noMessage: 'Hindi pa nasagot ang concern ko. Pwede mong explain sa ibang paraan?' }
  };

  function language(text) {
    if (/\b(unsa|unsay|unsaon|asa|akong|imong|nakatabang|nasulbad|nawagtang|dili|wala|gihapon|palihog|pangutana|mobayad|kwarta)\b/i.test(text)) return 'ceb';
    if (/\b(hindi|anong|mong|nakatulong|tanong|kailangan|puwede)\b/i.test(text)) return 'taglish';
    return 'en';
  }

  function suggestions(answer = '', category = 'General', topic = '') {
    const lang = language(answer || topic);
    const copy = labels[lang];
    const staff = { label: copy.staff, kind: 'staff' };
    if (/^(you're welcome|you are welcome|no problem|walay sapayan|walang anuman)[.!]/i.test(answer.trim())) return [];
    if (/did that answer your concern|did that help|nakatabang ba|nakatulong ba/i.test(answer)) {
      return [{ label: copy.yes, message: copy.yesMessage }, { label: copy.no, message: copy.noMessage }, staff];
    }
    const subject = `${topic} ${answer}`;
    let choices;
    if (/portal|password|log\s*in/i.test(subject) || category === 'ITCD') {
      choices = {
        en: [['Reset password', 'How do I reset my portal password?'], ['Still cannot log in', 'I still cannot log in to my portal.']],
        ceb: [['Reset password', 'Unsaon nako pag-reset sa akong portal password?'], ['Dili pa maka-log in', 'Dili gihapon ko maka-log in sa portal.']],
        taglish: [['Reset password', 'Paano ko i-reset ang portal password ko?'], ['Hindi pa maka-log in', 'Hindi pa rin ako maka-log in sa portal.']]
      }[lang];
    } else if (/transcript|\btor\b/i.test(subject)) {
      choices = {
        en: [['Requirements', 'What are the requirements for a transcript request?'], ['Processing time', 'How long does a transcript request take?']],
        ceb: [['Requirements', 'Unsay requirements sa pag-request og transcript?'], ['Processing time', 'Pila ka adlaw ang pag-process sa transcript?']],
        taglish: [['Requirements', 'Ano ang requirements para sa transcript request?'], ['Processing time', 'Gaano katagal ang transcript request?']]
      }[lang];
    } else if (category === 'Finance' || /tuition|payment|bayad|balance/i.test(subject)) {
      choices = {
        en: [['Check balance', 'Where can I check my tuition balance?'], ['Payment options', 'What payment options are available?']],
        ceb: [['Tan-aw sa balance', 'Asa nako makita ang akong tuition balance?'], ['Payment options', 'Unsay mga paagi sa pagbayad?']],
        taglish: [['Check balance', 'Saan ko makikita ang tuition balance ko?'], ['Payment options', 'Ano ang available na payment options?']]
      }[lang];
    } else if (category === 'Registrar') {
      choices = lang === 'ceb'
        ? [['Nawala nga student ID', 'Nawala akong student ID. Unsa akong buhaton?'], ['School documents', 'Unsaon nako pag-request og school document?']]
        : [['Lost student ID', 'I lost my student ID.'], ['School documents', 'I need help requesting a school document.']];
    } else if (category === 'Library') {
      choices = lang === 'ceb'
        ? [['Library clearance', 'Unsaon nako pagkuha og library clearance?'], ['Manghulam og libro', 'Unsaon nako paghulam og libro?']]
        : [['Library clearance', 'How do I get library clearance?'], ['Borrowing books', 'How can I borrow library books?']];
    } else if (category === 'Guidance') {
      choices = lang === 'ceb'
        ? [['Counseling request', 'Gusto ko magpa-appointment sa Guidance para sa counseling.']]
        : [['Counseling request', 'I would like to request a counseling appointment.']];
    } else {
      choices = lang === 'ceb'
        ? [['School documents', 'Kinahanglan ko og tabang sa pag-request og school document.'], ['Portal access', 'Dili ko ka-login sa akong student portal.']]
        : [['School documents', 'I need help requesting a school document.'], ['Portal access', 'I cannot access my student portal.']];
    }
    return [...choices.map(([label, message]) => ({ label, message })), staff];
  }

  function clear() {
    document.querySelectorAll('.ben-actions').forEach(node => node.remove());
  }

  function render(message, choices, onSelect) {
    clear();
    if (!message || !Array.isArray(choices)) return;
    const controls = (globalThis.HelpdeskLocator || document).createElement('div');
    controls.className = 'ben-actions';
    controls.setAttribute('role', 'group');
    controls.setAttribute('aria-label', 'Suggested replies');
    for (const choice of choices.slice(0, 3)) {
      if (!choice || typeof choice.label !== 'string' || (typeof choice.message !== 'string' && choice.kind !== 'staff')) continue;
      const button = (globalThis.HelpdeskLocator || document).createElement('button');
      button.type = 'button';
      button.className = 'ben-quick-reply';
      button.dataset.chatAction = choice.kind === 'staff' ? 'staff' : 'reply';
      if (choice.kind === 'staff') {
        const icon = (globalThis.HelpdeskLocator || document).createElement('img');
        icon.src = 'assets/icons/users.svg';
        icon.alt = '';
        icon.width = 13;
        icon.height = 13;
        button.appendChild(icon);
      }
      const label = (globalThis.HelpdeskLocator || document).createElement('span');
      label.textContent = choice.label.slice(0, 60);
      button.appendChild(label);
      button.addEventListener('click', () => {
        if (button.disabled) return;
        controls.querySelectorAll('button').forEach(node => { node.disabled = true; });
        onSelect(choice);
      });
      controls.appendChild(button);
    }
    if (!controls.childElementCount) return;
    const wrapper = message.querySelector('.bubble-wrap');
    if (wrapper) wrapper.insertBefore(controls, wrapper.querySelector('.time'));
  }

  async function readResponse(response, onDelta) {
    if (!response.headers?.get('content-type')?.includes('text/event-stream')) return response.json();
    if (!response.ok || !response.body) throw new Error('Ben could not start a reply.');
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let answer = '';
    let result;
    const consume = () => {
      let boundary;
      while ((boundary = /\r?\n\r?\n/.exec(buffer))) {
        const frame = buffer.slice(0, boundary.index);
        buffer = buffer.slice(boundary.index + boundary[0].length);
        let event = 'message';
        const data = [];
        for (const line of frame.split(/\r?\n/)) {
          if (line.startsWith('event:')) event = line.slice(6).trim();
          if (line.startsWith('data:')) data.push(line.slice(5).replace(/^ /, ''));
        }
        if (!data.length) continue;
        const payload = JSON.parse(data.join('\n'));
        if (event === 'delta' && typeof payload.text === 'string' && !result) {
          answer += payload.text;
          if (answer.length > 32000) throw new Error('Ben reply is too large.');
          onDelta(answer);
        } else if (event === 'done') {
          if (payload.success !== true || typeof payload.answer !== 'string' || !payload.answer.trim()) throw new Error('Invalid completed reply.');
          result = payload;
        } else if (event === 'error') {
          result = { success: false, error: payload.error || 'Ben could not finish this reply. Please try again.' };
        }
      }
      if (buffer.length > 1048576) throw new Error('Ben stream event is too large.');
    };
    try {
      while (!result) {
        const { value, done } = await reader.read();
        if (done) {
          buffer += decoder.decode();
          if (buffer.trim()) buffer += '\n\n';
          consume();
          break;
        }
        buffer += decoder.decode(value, { stream: true });
        consume();
      }
      if (!result) throw new Error('Ben reply was interrupted. Please try again.');
      return result;
    } finally {
      await reader.cancel().catch(() => {});
      reader.releaseLock();
    }
  }

  return { suggestions, clear, render, readResponse };
})();
