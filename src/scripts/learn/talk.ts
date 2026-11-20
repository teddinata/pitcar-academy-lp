import { api, apiStream, ApiError } from './api';

type Lesson = { id: number; title: string };
type Ref = { id: number; author: string; removed?: boolean };
type Post = {
  id: number;
  author: string;
  team: boolean;
  mine: boolean;
  body: string;
  created_at: string;
  lesson?: Lesson;
  reply_count?: number;
  answered?: boolean;
  reply_to?: Ref;
  reported?: boolean;
};
type Page = { total: number; class_total: number; has_more: boolean; threads: Post[] };
type Turn = { role: 'user' | 'assistant'; text: string };

const el = <K extends keyof HTMLElementTagNameMap>(tag: K, className = '', text = ''): HTMLElementTagNameMap[K] => {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text) node.textContent = text;
  return node;
};

const problem = (e: unknown, fallback: string) =>
  e instanceof ApiError ? (e.status === 429 && !e.message.includes('Jatah') ? 'Terlalu sering. Tunggu satu menit, lalu coba lagi.' : e.message) : fallback;

const relative = new Intl.RelativeTimeFormat('id', { numeric: 'auto' });
const dateOnly = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

function ago(iso: string): string {
  const seconds = (Date.parse(iso) - Date.now()) / 1000;
  const abs = Math.abs(seconds);
  if (abs < 60) return 'baru saja';
  if (abs < 3600) return relative.format(Math.round(seconds / 60), 'minute');
  if (abs < 86400) return relative.format(Math.round(seconds / 3600), 'hour');
  if (abs < 7 * 86400) return relative.format(Math.round(seconds / 86400), 'day');
  return dateOnly.format(new Date(iso));
}

/** A small, safe Markdown subset for AI answers: paragraphs, lists, bold, italic, code. */
export function markdown(source: string): string {
  const escape = (s: string) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!);
  const inline = (s: string) =>
    escape(s)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
      .replace(/`([^`]+)`/g, '<code>$1</code>');

  const out: string[] = [];
  let list: 'ol' | 'ul' | null = null;
  let paragraph: string[] = [];
  const flushParagraph = () => {
    if (paragraph.length) out.push(`<p>${paragraph.join('<br>')}</p>`);
    paragraph = [];
  };
  const closeList = () => {
    if (list) out.push(`</${list}>`);
    list = null;
  };

  for (const raw of source.replace(/\r/g, '').split('\n')) {
    const line = raw.trim();
    const ordered = /^\d+[.)]\s+(.*)$/.exec(line);
    const bullet = /^[-*•]\s+(.*)$/.exec(line);
    const heading = /^#{1,6}\s+(.*)$/.exec(line);

    if (!line) {
      flushParagraph();
      closeList();
    } else if (ordered || bullet) {
      flushParagraph();
      const kind = ordered ? 'ol' : 'ul';
      if (list !== kind) {
        closeList();
        out.push(`<${kind}>`);
        list = kind;
      }
      out.push(`<li>${inline((ordered ?? bullet)![1])}</li>`);
    } else if (heading) {
      flushParagraph();
      closeList();
      out.push(`<p><strong>${inline(heading[1])}</strong></p>`);
    } else {
      closeList();
      paragraph.push(inline(line));
    }
  }
  flushParagraph();
  closeList();
  return out.join('');
}

export function mountTalk(root: HTMLElement, slug: string) {
  const $ = <T extends HTMLElement>(sel: string) => root.querySelector<T>(sel)!;
  const path = `/learn/courses/${encodeURIComponent(slug)}`;
  let lesson: Lesson | null = null;

  /* ---------- Tabs ---------- */
  const tabs = [...root.querySelectorAll<HTMLButtonElement>('[role="tab"]')];
  function select(tab: HTMLButtonElement, focus = false) {
    for (const t of tabs) {
      const on = t === tab;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      $(`[data-panel="${t.dataset.tab}"]`).hidden = !on;
    }
    if (focus) tab.focus();
    if (tab.dataset.tab === 'ai') startAi();
    else startDiscussion();
  }
  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => select(tab));
    tab.addEventListener('keydown', (e) => {
      const step = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
      if (step) {
        e.preventDefault();
        select(tabs[(i + step + tabs.length) % tabs.length], true);
      }
    });
  });

  /* ---------- Discussion ---------- */
  const status = $('[data-discuss-status]');
  const list = $<HTMLOListElement>('[data-threads]');
  const more = $<HTMLButtonElement>('[data-more]');
  const count = $('[data-discuss-count]');
  const compose = $<HTMLFormElement>('[data-compose]');
  const composeOpen = $<HTMLButtonElement>('[data-compose-open]');
  const composeError = $('[data-compose-error]');
  const composeSubmit = $<HTMLButtonElement>('[data-compose-submit]');
  let scope: 'lesson' | 'all' = 'lesson';
  let offset = 0;
  let started = false;
  let loading = 0;

  function startDiscussion() {
    if (started) return;
    started = true;
    loadThreads(true);
  }

  async function loadThreads(reset: boolean) {
    const ticket = ++loading;
    if (reset) {
      offset = 0;
      status.textContent = 'Memuat diskusi.';
      status.hidden = false;
      list.hidden = true;
      more.hidden = true;
    }
    more.disabled = true;
    try {
      const query = new URLSearchParams({ offset: String(offset) });
      if (scope === 'lesson' && lesson) query.set('lesson', String(lesson.id));
      const page = await api<Page>(`${path}/discussions?${query}`);
      if (ticket !== loading) return;
      count.textContent = page.class_total ? `(${page.class_total})` : '';
      if (reset) list.replaceChildren();
      list.append(...page.threads.map(threadRow));
      offset += page.threads.length;
      list.hidden = list.childElementCount === 0;
      more.hidden = !page.has_more;
      renderEmpty(page);
    } catch (e) {
      if (ticket !== loading) return;
      status.textContent = problem(e, 'Diskusi tidak bisa dimuat. Coba lagi sebentar lagi.');
      status.hidden = false;
    } finally {
      more.disabled = false;
    }
  }

  function renderEmpty(page: Page) {
    if (list.childElementCount > 0) {
      status.hidden = true;
      return;
    }
    status.hidden = false;
    status.replaceChildren();
    if (scope === 'lesson' && page.class_total > 0) {
      const link = el('button', 'talk-link talk-link--strong', `lihat ${page.class_total} diskusi di semua video`);
      link.type = 'button';
      link.addEventListener('click', () => setScope('all'));
      status.append('Belum ada pertanyaan di video ini. Tanyakan yang belum jelas, atau ', link, '.');
    } else {
      status.textContent = scope === 'lesson' ? 'Belum ada pertanyaan di video ini. Tanyakan yang belum jelas dari videonya.' : 'Belum ada diskusi di kelas ini. Tanyakan yang belum jelas dari videonya.';
    }
  }

  function setScope(next: 'lesson' | 'all') {
    scope = next;
    root.querySelectorAll<HTMLInputElement>('input[name="talk-scope"]').forEach((r) => (r.checked = r.value === next));
    loadThreads(true);
  }
  root.querySelectorAll<HTMLInputElement>('input[name="talk-scope"]').forEach((r) =>
    r.addEventListener('change', () => r.checked && setScope(r.value as 'lesson' | 'all')),
  );
  more.addEventListener('click', () => loadThreads(false));

  function meta(post: Post, withLesson: boolean) {
    const row = el('p', 'talk-meta');
    row.append(el('strong', '', post.author));
    if (post.team) row.append(el('span', 'talk-team', 'Tim Pitcar'));
    const time = el('time', '', ago(post.created_at));
    time.dateTime = post.created_at;
    row.append(time);
    if (post.reply_to) row.append(el('span', '', post.reply_to.removed ? 'membalas pesan yang sudah dihapus' : `membalas ${post.reply_to.author}`));
    if (withLesson && post.lesson) row.append(el('span', '', post.lesson.title));
    if (post.answered) row.append(el('span', '', 'Dijawab tim'));
    return row;
  }

  function linkButton(text: string, onClick: (b: HTMLButtonElement) => void, strong = false) {
    const b = el('button', 'talk-link' + (strong ? ' talk-link--strong' : ''), text);
    b.type = 'button';
    b.addEventListener('click', () => onClick(b));
    return b;
  }

  /**
   * Report and delete ask for confirmation in place, not in a dialog. They
   * live in their own slot, so the reply toggle beside them stays put.
   */
  function moderation(post: Post, actions: HTMLElement, onRemoved: () => void) {
    const slot = el('span', 'contents');
    const note = (text: string) => el('span', 'inline-flex min-h-11 items-center text-sm text-muted', text);
    if (post.mine) {
      slot.append(
        linkButton('Hapus', () => confirmInline(slot, 'Hapus pesanmu? Ini tidak bisa dibatalkan.', 'Hapus', null, async () => {
          await api(`/learn/discussions/${post.id}/delete`, { method: 'POST' });
          onRemoved();
        })),
      );
    } else if (post.reported) {
      slot.append(note('Sudah kamu laporkan'));
    } else {
      slot.append(
        linkButton('Laporkan', () => confirmInline(slot, 'Laporkan pesan ini ke tim Pitcar?', 'Laporkan', 'Alasan (boleh dikosongkan)', async (reason) => {
          await api(`/learn/discussions/${post.id}/report`, { method: 'POST', body: { reason } });
          post.reported = true;
          slot.replaceChildren(note('Dilaporkan. Tim akan memeriksanya.'));
        })),
      );
    }
    actions.append(slot);
  }

  function confirmInline(actions: HTMLElement, question: string, verb: string, reasonLabel: string | null, run: (reason: string) => Promise<void>) {
    const saved = [...actions.childNodes];
    const box = el('form', 'talk-inline talk-inline--row');
    box.noValidate = true;
    box.append(el('p', 'text-sm font-semibold', question));
    let reason: HTMLInputElement | null = null;
    if (reasonLabel) {
      const id = `talk-reason-${Math.random().toString(36).slice(2)}`;
      const label = el('label', 'mt-2 block text-sm text-muted', reasonLabel);
      label.htmlFor = id;
      reason = el('input', 'learn-input mt-1');
      reason.id = id;
      reason.maxLength = 300;
      box.append(label, reason);
    }
    const error = el('p', 'learn-error mt-2');
    error.setAttribute('role', 'alert');
    error.hidden = true;
    const row = el('div', 'mt-3 flex flex-wrap gap-3');
    const go = el('button', 'btn btn-primary', verb);
    go.type = 'submit';
    const cancel = el('button', 'btn btn-ghost', 'Batal');
    cancel.type = 'button';
    row.append(go, cancel);
    box.append(error, row);
    actions.replaceChildren(box);
    (reason ?? go).focus();

    cancel.addEventListener('click', () => actions.replaceChildren(...saved));
    box.addEventListener('submit', async (e) => {
      e.preventDefault();
      go.disabled = true;
      error.hidden = true;
      try {
        await run(reason?.value.trim() ?? '');
      } catch (err) {
        error.textContent = problem(err, 'Belum berhasil. Coba lagi.');
        error.hidden = false;
        go.disabled = false;
      }
    });
  }

  function threadRow(post: Post): HTMLLIElement {
    const li = el('li', 'talk-thread');
    const article = el('article');
    article.setAttribute('aria-label', `Pertanyaan dari ${post.author}`);
    const body = el('p', 'talk-body', post.body);
    const actions = el('div', 'talk-actions');
    const replies = el('div');
    let open = false;

    const toggleLabel = () => (open ? 'Tutup balasan' : post.reply_count ? `${post.reply_count} balasan` : 'Balas');
    const toggle = linkButton(toggleLabel(), async () => {
      open = !open;
      toggle.setAttribute('aria-expanded', String(open));
      toggle.textContent = toggleLabel();
      if (!open) return replies.replaceChildren();
      replies.replaceChildren(el('p', 'mt-3 text-sm text-muted', 'Memuat balasan.'));
      try {
        const data = await api<{ thread: Post; replies: Post[] }>(`/learn/discussions/${post.id}`);
        renderReplies(post, data.replies, replies, () => {
          post.reply_count = replies.querySelectorAll('.talk-reply').length;
        });
      } catch (e) {
        replies.replaceChildren(el('p', 'learn-error mt-3', problem(e, 'Balasan tidak bisa dimuat.')));
      }
    }, true);
    toggle.setAttribute('aria-expanded', 'false');
    actions.append(toggle);
    moderation(post, actions, () => li.remove());

    article.append(meta(post, scope === 'all'), body, actions, replies);
    li.append(article);
    return li;
  }

  function replyRow(post: Post, onReply: (p: Post) => void, onRemoved: () => void): HTMLLIElement {
    const li = el('li', 'talk-reply');
    const actions = el('div', 'talk-actions');
    actions.append(linkButton('Balas', () => onReply(post)));
    moderation(post, actions, () => {
      li.remove();
      onRemoved();
    });
    li.append(meta(post, false), el('p', 'talk-body', post.body), actions);
    return li;
  }

  function renderReplies(thread: Post, posts: Post[], into: HTMLElement, changed: () => void) {
    const ol = el('ol', 'talk-replies');
    const form = el('form', 'talk-inline');
    form.noValidate = true;
    const id = `talk-reply-${thread.id}`;
    const label = el('label', 'text-sm font-semibold', 'Balasanmu');
    label.htmlFor = id;
    const target = el('p', 'text-sm text-muted');
    target.hidden = true;
    const textarea = el('textarea', 'learn-input mt-1.5');
    textarea.id = id;
    textarea.maxLength = 2000;
    textarea.rows = 3;
    const error = el('p', 'learn-error mt-2');
    error.setAttribute('role', 'alert');
    error.hidden = true;
    const send = el('button', 'btn btn-primary mt-3', 'Kirim balasan');
    send.type = 'submit';
    form.append(label, target, textarea, error, send);
    let replyTo: Post | null = null;

    const aim = (p: Post | null) => {
      replyTo = p;
      target.replaceChildren();
      target.hidden = !p;
      if (p) {
        target.append(`Membalas ${p.author} `, linkButton('Batal', () => aim(null)));
        textarea.focus();
      }
    };
    const add = (p: Post) => ol.append(replyRow(p, aim, changed));
    posts.forEach(add);
    ol.hidden = posts.length === 0;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const body = textarea.value.trim();
      if (body.length < 2) {
        error.textContent = 'Tulis balasanmu dulu.';
        error.hidden = false;
        return textarea.focus();
      }
      error.hidden = true;
      send.disabled = true;
      send.textContent = 'Mengirim';
      try {
        const res = await api<{ reply: Post }>(`/learn/discussions/${thread.id}/replies`, { method: 'POST', body: { body, reply_to_id: replyTo?.id ?? null } });
        add(res.reply);
        ol.hidden = false;
        textarea.value = '';
        aim(null);
        changed();
      } catch (err) {
        error.textContent = problem(err, 'Balasan belum terkirim. Coba lagi.');
        error.hidden = false;
      } finally {
        send.disabled = false;
        send.textContent = 'Kirim balasan';
      }
    });
    textarea.addEventListener('input', () => (error.hidden = true));

    into.replaceChildren(ol, form);
  }

  composeOpen.addEventListener('click', () => {
    compose.hidden = false;
    composeOpen.hidden = true;
    compose.querySelector<HTMLTextAreaElement>('textarea')!.focus();
  });
  $('[data-compose-cancel]').addEventListener('click', () => {
    compose.hidden = true;
    composeOpen.hidden = false;
    composeError.hidden = true;
    composeOpen.focus();
  });
  compose.addEventListener('input', () => (composeError.hidden = true));
  compose.addEventListener('submit', async (e) => {
    e.preventDefault();
    const textarea = compose.querySelector<HTMLTextAreaElement>('textarea')!;
    const body = textarea.value.trim();
    if (body.length < 3) {
      composeError.textContent = 'Tulis pertanyaanmu dulu.';
      composeError.hidden = false;
      return textarea.focus();
    }
    const aboutLesson = compose.querySelector<HTMLInputElement>('[name="about_lesson"]')!.checked && lesson;
    composeSubmit.disabled = true;
    composeSubmit.textContent = 'Mengirim';
    try {
      await api<{ thread: Post }>(`${path}/discussions`, { method: 'POST', body: { body, lesson_id: aboutLesson ? lesson!.id : null } });
      textarea.value = '';
      compose.hidden = true;
      composeOpen.hidden = false;
      if (scope === 'lesson' && !aboutLesson) setScope('all');
      else await loadThreads(true);
      list.querySelector<HTMLElement>('.talk-thread')?.setAttribute('tabindex', '-1');
      list.querySelector<HTMLElement>('.talk-thread')?.focus();
    } catch (err) {
      composeError.textContent = problem(err, 'Pertanyaan belum terkirim. Coba lagi.');
      composeError.hidden = false;
    } finally {
      composeSubmit.disabled = false;
      composeSubmit.textContent = 'Kirim';
    }
  });

  /* ---------- AI Pitcar Academy ---------- */
  const log = $<HTMLOListElement>('[data-ai-log]');
  const aiForm = $<HTMLFormElement>('[data-ai-form]');
  const aiInput = aiForm.querySelector<HTMLTextAreaElement>('textarea')!;
  const aiSubmit = $<HTMLButtonElement>('[data-ai-submit]');
  const aiError = $('[data-ai-error]');
  const aiLeft = $('[data-ai-left]');
  const aiNew = $<HTMLButtonElement>('[data-ai-new]');
  const suggest = $('[data-ai-suggest]');
  let aiStarted = false;
  let aiEnabled = true;
  let asking = false;

  async function startAi() {
    if (aiStarted) return;
    aiStarted = true;
    aiLeft.textContent = 'Memuat.';
    try {
      const data = await api<{ enabled: boolean; limit: number; remaining: number; messages: Turn[] }>(`${path}/ai`);
      aiEnabled = data.enabled;
      data.messages.forEach((m) => log.append(turn(m.role, m.text)));
      log.hidden = data.messages.length === 0;
      aiNew.hidden = data.messages.length === 0;
      setLeft(data.remaining, data.limit);
      if (!aiEnabled) {
        // Not switched on yet is a state, not an error: say so plainly and drop the form.
        $('[data-ai-intro]').textContent = 'AI Pitcar Academy belum aktif di kelas ini. Sementara itu, tanyakan hal yang belum jelas di Diskusi kelas.';
        aiForm.hidden = true;
      }
    } catch (e) {
      aiStarted = false;
      aiLeft.textContent = '';
      showAiError(problem(e, 'AI Pitcar Academy tidak bisa dimuat. Coba lagi sebentar lagi.'));
    }
    renderSuggestions();
    lockAi(false);
  }

  let limit = 0;
  let remaining = 0;
  function setLeft(left: number, max = limit) {
    remaining = left;
    limit = max;
    aiLeft.textContent = left > 0 ? `Sisa ${left} dari ${max} pertanyaan hari ini` : `Jatah ${max} pertanyaan hari ini sudah habis. Bisa bertanya lagi besok.`;
  }

  function showAiError(message: string) {
    aiError.textContent = message;
    aiError.hidden = false;
  }

  function lockAi(on: boolean) {
    asking = on;
    const off = on || !aiEnabled || remaining <= 0;
    aiSubmit.disabled = off;
    aiInput.disabled = !aiEnabled;
    aiSubmit.textContent = on ? 'Menjawab' : 'Tanya';
    suggest.querySelectorAll<HTMLButtonElement>('button').forEach((b) => (b.disabled = off));
  }

  function renderSuggestions() {
    const empty = log.childElementCount === 0;
    suggest.hidden = !empty || !aiEnabled;
    $('[data-ai-intro]').hidden = !empty && aiEnabled;
    if (!empty) return;
    // The AI is told which video is open, so the suggestions can just say "video ini".
    suggest.replaceChildren(
      ...[
        'Jelaskan ulang video ini dengan bahasa yang lebih sederhana.',
        'Apa kesalahan yang sering terjadi saat mengerjakan materi video ini?',
        'Buatkan 5 pertanyaan latihan dari video ini.',
      ].map((text) => {
        const b = el('button', 'talk-suggest', text);
        b.type = 'button';
        b.addEventListener('click', () => ask(text));
        return b;
      }),
    );
  }

  function turn(role: 'user' | 'assistant', text: string) {
    const li = el('li', 'talk-turn');
    li.append(el('p', 'talk-who', role === 'user' ? 'Kamu' : 'AI Pitcar Academy'));
    if (role === 'user') li.append(el('p', 'talk-question', text));
    else {
      const answer = el('div', 'talk-answer');
      answer.innerHTML = markdown(text);
      li.append(answer);
    }
    return li;
  }

  async function ask(question: string) {
    if (asking || !aiEnabled) return;
    aiError.hidden = true;
    lockAi(true);
    log.hidden = false;
    suggest.hidden = true;
    $('[data-ai-intro]').hidden = true;
    log.append(turn('user', question));
    const reply = turn('assistant', '');
    const answer = reply.querySelector<HTMLElement>('.talk-answer')!;
    answer.classList.add('talk-caret');
    answer.setAttribute('aria-busy', 'true');
    log.append(reply);
    aiInput.value = '';

    let text = '';
    let frame = 0;
    const paint = () => {
      frame = 0;
      answer.innerHTML = markdown(text);
    };
    let failed: string | null = null;

    try {
      await apiStream(`${path}/ai`, { question, lesson_id: lesson?.id ?? null }, (event, data) => {
        if (event === 'delta') {
          text += data.text;
          if (!frame) frame = requestAnimationFrame(paint);
        } else if (event === 'done') {
          text = data.text;
          setLeft(data.remaining);
        } else if (event === 'error') {
          failed = data.message;
        }
      });
    } catch (e) {
      failed = problem(e, 'AI Pitcar Academy sedang tidak bisa menjawab. Coba lagi sebentar lagi.');
    }

    if (frame) cancelAnimationFrame(frame);
    answer.classList.remove('talk-caret');
    answer.removeAttribute('aria-busy');
    if (failed && !text) {
      reply.remove();
      log.lastElementChild?.remove();
      log.hidden = log.childElementCount === 0;
      aiInput.value = question;
      showAiError(failed);
      if (log.hidden) renderSuggestions();
    } else {
      paint();
      aiNew.hidden = false;
    }
    lockAi(false);
  }

  aiForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const question = aiInput.value.trim();
    if (question.length < 3) {
      showAiError('Tulis pertanyaanmu dulu.');
      return aiInput.focus();
    }
    ask(question);
  });
  aiInput.addEventListener('input', () => (aiError.hidden = true));
  aiInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) aiForm.requestSubmit();
  });
  aiNew.addEventListener('click', async () => {
    if (asking) return;
    aiNew.disabled = true;
    try {
      await api(`${path}/ai/new`, { method: 'POST' });
      log.replaceChildren();
      log.hidden = true;
      aiNew.hidden = true;
      renderSuggestions();
      lockAi(false);
      aiInput.focus();
    } catch (e) {
      showAiError(problem(e, 'Belum berhasil. Coba lagi.'));
    } finally {
      aiNew.disabled = false;
    }
  });

  /* ---------- Wiring ---------- */
  // The discussion loads when the section comes near, not with the video.
  if ('IntersectionObserver' in window) {
    const seen = new IntersectionObserver((entries) => {
      if (entries.some((e) => e.isIntersecting)) {
        seen.disconnect();
        if ($('[data-panel="discuss"]').hidden === false) startDiscussion();
      }
    }, { rootMargin: '300px 0px' });
    seen.observe(root);
  } else startDiscussion();

  return {
    setLesson(next: Lesson) {
      const changed = lesson?.id !== next.id;
      lesson = next;
      $('[data-compose-lesson]').textContent = next.title;
      if (changed && started && scope === 'lesson') loadThreads(true);
      if (aiStarted && log.childElementCount === 0) renderSuggestions();
    },
  };
}
