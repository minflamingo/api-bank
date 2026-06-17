<script>
document.addEventListener('DOMContentLoaded', function () {
  const page = document.querySelector('[data-bank-history-page]');
  if (!page || page.dataset.partyAjaxReady === '1') return;
  page.dataset.partyAjaxReady = '1';

  const content = page.querySelector('[data-bank-history-content]');
  if (!content) return;

  let timer = null;
  let controller = null;

  function formUrl(form) {
    const data = new FormData(form);
    const params = new URLSearchParams();
    data.forEach(function (value, key) {
      if (value !== '') params.append(key, value);
    });
    const query = params.toString();
    return form.action + (query ? ('?' + query) : '');
  }

  function loadHistory(url, push) {
    if (controller) controller.abort();
    controller = new AbortController();
    page.classList.add('opacity-50');

    fetch(url, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'text/html'
      },
      credentials: 'same-origin',
      signal: controller.signal
    })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.text();
      })
      .then(function (html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const next = doc.querySelector('[data-bank-history-content]');
        if (!next) {
          window.location.href = url;
          return;
        }
        content.innerHTML = next.innerHTML;
        if (push) window.history.pushState({}, '', url);
      })
      .catch(function (error) {
        if (error.name !== 'AbortError') window.location.href = url;
      })
      .finally(function () {
        page.classList.remove('opacity-50');
      });
  }

  document.addEventListener('submit', function (event) {
    const form = event.target.closest('[data-bank-history-page] .js-bank-party-filter');
    if (!form) return;
    event.preventDefault();
    loadHistory(formUrl(form), true);
  });

  document.addEventListener('input', function (event) {
    const input = event.target.closest('[data-bank-history-page] .js-bank-party-filter input[name="party_name"]');
    if (!input || event.isComposing) return;
    window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      const form = input.closest('form');
      if (form) loadHistory(formUrl(form), true);
    }, 450);
  });

  document.addEventListener('click', function (event) {
    const link = event.target.closest('[data-bank-history-page] .js-bank-party-clear');
    if (!link) return;
    event.preventDefault();
    loadHistory(link.href, true);
  });

  window.addEventListener('popstate', function () {
    loadHistory(window.location.href, false);
  });
});
</script>
