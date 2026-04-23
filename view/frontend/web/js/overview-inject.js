/**
 * Auto-injects AI overview container (WordPress / Drupal parity). Reads window.SSAI_OVERVIEW_INJECT.
 */
(function () {
  'use strict';
  var cfg = window.SSAI_OVERVIEW_INJECT;
  if (!cfg || !cfg.searchQuery || cfg.searchQuery.length < 3) {
    return;
  }
  if (document.getElementById('ssai-auto-overview')) {
    return;
  }

  var container = document.createElement('div');
  container.id = 'ssai-auto-overview';
  container.className = 'ssai-overview-container ssai-auto-injected ssai-overview-loading';
  container.setAttribute('data-query', cfg.searchQuery);
  var titleEl = document.createElement('h2');
  titleEl.className = 'ssai-overview-title';
  titleEl.textContent = cfg.title || '';
  var contentWrap = document.createElement('div');
  contentWrap.className = 'ssai-overview-content';
  container.appendChild(titleEl);
  container.appendChild(contentWrap);

  var inserted = false;
  var targetSelector = cfg.overviewTargetSelector || '';
  var resultsPageSelector = cfg.resultsPageSelector || '';

  function findInsertPoint(scope) {
    var s = scope || document;
    var searchBarSelectors = [
      'form[role="search"]',
      '.search-form', 'form.search-form',
      '.algolia-search-box-wrapper',
      '.search-box', '.search-wrapper',
      '[class*="search-box"]', '[class*="search-form"]'
    ];
    for (var i = 0; i < searchBarSelectors.length; i++) {
      var el = s.querySelector(searchBarSelectors[i]);
      if (el && (!scope || scope.contains(el))) {
        return el;
      }
    }
    var searchInput = s.querySelector('input[type="search"]');
    if (searchInput && (!scope || scope.contains(searchInput))) {
      return searchInput.closest('form') || searchInput.parentElement;
    }
    searchInput = s.querySelector('input[name="q"]');
    if (searchInput && (!scope || scope.contains(searchInput))) {
      return searchInput.closest('form') || searchInput.parentElement;
    }
    return null;
  }

  function insertAfterFn(newNode, afterEl, parentEl) {
    if (afterEl && afterEl.nextSibling) {
      parentEl.insertBefore(newNode, afterEl.nextSibling);
    } else if (afterEl) {
      parentEl.appendChild(newNode);
    } else {
      parentEl.insertBefore(newNode, parentEl.firstChild);
    }
  }

  function doInsert(skipTargetSelector) {
    if (inserted) {
      return true;
    }
    if (targetSelector && !skipTargetSelector) {
      var target = document.querySelector(targetSelector);
      if (target) {
        var searchBar = findInsertPoint(target);
        insertAfterFn(container, searchBar, target);
        inserted = true;
        return true;
      }
      return false;
    }
    if (resultsPageSelector) {
      var selectors = resultsPageSelector.split(/[\n,]+/).map(function (s) { return s.trim(); }).filter(Boolean);
      for (var i = 0; i < selectors.length; i++) {
        var si = document.querySelector(selectors[i]);
        if (si) {
          var form = si.closest('form');
          var insertAfterEl = form || si.parentElement;
          if (insertAfterEl && insertAfterEl.parentElement) {
            insertAfterFn(container, insertAfterEl, insertAfterEl.parentElement);
            inserted = true;
            return true;
          }
        }
      }
    }
    var searchContainers = [
      '#ais-main', 'main', '#content', '.content-area', '#main', '.main',
      '.algolia-search-box-wrapper',
      '.search-results', '#search-results', '[class*="search-result"]',
      'main .entry-content',
      '#algolia-hits', '.ais-Hits'
    ];
    var hitsContainers = ['#algolia-hits', '.ais-Hits'];
    for (var j = 0; j < searchContainers.length; j++) {
      var t = document.querySelector(searchContainers[j]);
      if (t) {
        var sb = findInsertPoint(t);
        if (hitsContainers.indexOf(searchContainers[j]) >= 0 && !sb) {
          var parent = t.parentElement;
          if (parent) {
            parent.insertBefore(container, t);
            inserted = true;
            return true;
          }
        }
        insertAfterFn(container, sb, t);
        inserted = true;
        return true;
      }
    }
    var anySearchBar = findInsertPoint();
    if (anySearchBar && anySearchBar.parentElement) {
      insertAfterFn(container, anySearchBar, anySearchBar.parentElement);
      inserted = true;
      return true;
    }
    document.body.insertBefore(container, document.body.firstChild);
    inserted = true;
    return true;
  }

  if (doInsert()) {
    return;
  }
  var waitTimeout = setTimeout(function () {
    observer.disconnect();
    doInsert(true);
  }, 5000);
  var observer = new MutationObserver(function () {
    if (doInsert()) {
      clearTimeout(waitTimeout);
      observer.disconnect();
    }
  });
  observer.observe(document.body, { childList: true, subtree: true });
})();
