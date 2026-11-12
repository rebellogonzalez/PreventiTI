import { normalizeSearch as normalize } from '../lib/search.js';

const instances = new WeakMap();

function itemValue(item) {
  return String(item.value ?? item.id ?? '');
}

function itemLabel(item) {
  const code = String(item.code ?? item.asset_code ?? '').trim();
  const name = String(item.name ?? item.asset_name ?? 'Dispositivo sin nombre').trim();
  return code ? `[${code}] ${name}` : name;
}

function categoryName(item, fallback) {
  return String(item.category_name ?? item.asset_category ?? item.category ?? '').trim() || fallback;
}

export function enhanceOwnerSelect(select, users, selectedValue) {
  const items = users.map(user => ({
    id: user.id, name: `${user.name} (${user.email})`, searchName: normalize(user.name),
  }));
  items.push({ id: 'other', name: 'Área u otro responsable', category: 'Otras opciones' });
  return enhanceAssetSelect(select, items, {
    selectedValue, fallbackCategory: 'Usuarios', listLabel: 'Opciones de usuario asignado',
    emptyLabel: 'Sin asignar', showEmptyOption: 'unfiltered',
    searchPlaceholder: 'Buscar por nombre…', help: 'Escribí el comienzo del nombre y elegí una opción.',
    matches: (item, query) => item.searchName?.startsWith(query),
    noResults: 'No se encontraron usuarios con ese nombre.',
    invalidSelection: 'Elegí un usuario de la lista o borrá la búsqueda para continuar sin asignar.',
  });
}

export function enhanceAssetSelect(select, items, settings = {}) {
  if (!select) return null;
  const existing = instances.get(select);
  if (existing) {
    existing.update(items, settings);
    return existing;
  }

  const component = new SearchableAssetSelect(select, items, settings);
  instances.set(select, component);
  return component;
}

class SearchableAssetSelect {
  constructor(select, items, settings) {
    this.select = select;
    this.settings = {};
    this.items = [];
    this.open = false;
    this.fitListbox = this.fitListbox.bind(this);
    this.query = '';
    this.suppressInputSync = false;
    this.activeValue = null;
    this.originalRequired = select.required;

    const wrapper = document.createElement('div');
    wrapper.className = 'asset-select';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    this.wrapper = wrapper;

    const inputShell = document.createElement('div');
    inputShell.className = 'asset-select__input-shell';
    wrapper.appendChild(inputShell);

    this.input = document.createElement('input');
    this.input.type = 'search';
    this.input.className = 'asset-select__input';
    this.input.autocomplete = 'off';
    this.input.spellcheck = false;
    this.input.setAttribute('role', 'combobox');
    this.input.setAttribute('aria-autocomplete', 'list');
    this.input.setAttribute('aria-haspopup', 'listbox');
    this.input.setAttribute('aria-expanded', 'false');
    this.input.id = `${select.id}-search`;
    inputShell.appendChild(this.input);

    this.toggle = document.createElement('button');
    this.toggle.type = 'button';
    this.toggle.className = 'asset-select__toggle';
    this.toggle.tabIndex = -1;
    this.toggle.setAttribute('aria-label', settings.listLabel || 'Mostrar dispositivos por categoría');
    this.toggle.setAttribute('aria-hidden', 'true');
    this.toggle.textContent = '▾';
    inputShell.appendChild(this.toggle);

    this.help = document.createElement('span');
    this.help.className = 'asset-select__help';
    this.help.id = `${select.id}-help`;
    this.help.textContent = settings.help || 'Busca por código, nombre, categoría o ubicación, o explora los grupos.';
    wrapper.appendChild(this.help);

    this.listbox = document.createElement('div');
    this.listbox.className = 'asset-select__listbox';
    this.listbox.id = `${select.id}-listbox`;
    this.listbox.setAttribute('role', 'listbox');
    this.listbox.setAttribute('aria-label', settings.listLabel || 'Dispositivos por categoría');
    this.listbox.hidden = true;
    wrapper.appendChild(this.listbox);

    select.classList.add('asset-select__native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.removeAttribute('required');
    select.dataset.searchableAssetSelect = 'true';
    this.input.setAttribute('aria-controls', this.listbox.id);
    this.input.setAttribute('aria-describedby', this.help.id);
    this.input.required = this.originalRequired;
    if (this.originalRequired) this.input.setAttribute('aria-required', 'true');

    const label = [...document.querySelectorAll('label[for]')].find(candidate => candidate.htmlFor === select.id);
    if (label) {
      label.htmlFor = this.input.id;
      this.input.setAttribute('aria-labelledby', label.id || this.ensureLabelId(label));
    } else {
      this.input.setAttribute('aria-label', settings.label || 'Dispositivo');
    }

    this.input.placeholder = settings.searchPlaceholder || 'Escribe para buscar un dispositivo…';
    this.input.addEventListener('focus', () => {
      this.syncInputFromValue();
      if (this.input.value) this.input.select();
      this.show();
    });
    this.input.addEventListener('click', () => { if (!this.open) this.show(); });
    wrapper.closest('.modal-backdrop')?.addEventListener('close', () => this.hide());
    this.input.addEventListener('input', () => this.handleInput());
    this.input.addEventListener('invalid', () => this.input.setAttribute('aria-invalid', 'true'));
    this.input.addEventListener('keydown', event => this.handleKeydown(event));
    this.input.addEventListener('blur', () => {
      window.setTimeout(() => {
        if (this.pickerDialog?.open || this.wrapper.contains(document.activeElement)) return;
        this.hide();
        this.syncInputFromValue();
      }, 0);
    });
    this.toggle.addEventListener('pointerdown', event => event.preventDefault());
    this.toggle.addEventListener('click', () => {
      if (this.open) this.hide();
      else {
        this.input.focus();
        this.show();
      }
    });
    // Keep mouse selection and category headings from blurring the search.
    // Leave pointer/touch scrolling available on touchscreens.
    this.listbox.addEventListener('mousedown', event => {
      if (event.target.closest('[role="option"], [role="group"]')) event.preventDefault();
    });
    this.listbox.addEventListener('click', event => {
      const option = event.target.closest('[role="option"]');
      if (option) this.choose(option.dataset.value);
    });
    this.listbox.addEventListener('mousemove', event => {
      const option = event.target.closest('[role="option"]');
      if (option) this.setActive(option.dataset.value);
    });
    this.select.addEventListener('change', () => {
      if (!this.suppressInputSync) {
        this.query = '';
        this.syncInputFromValue();
        if (this.open) this.renderOptions('');
      }
      this.updateValidity();
      this.markSelectedOption();
    });
    this.select.form?.addEventListener('reset', event => {
      window.setTimeout(() => {
        if (event.defaultPrevented) return;
        if (this.pickerDialog?.open) this.pickerDialog.close();
        this.query = '';
        this.hide();
        this.input.removeAttribute('aria-invalid');
        this.syncInputFromValue();
        this.updateValidity();
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
      }, 0);
    });

    if (settings.pickerDialog && settings.pickerTrigger) {
      this.initPickerDialog(settings.pickerDialog, settings.pickerTrigger, label);
    }
    this.update(items, settings, settings.selectedValue ?? select.value);
  }

  initPickerDialog(dialog, trigger, label) {
    this.pickerDialog = dialog;
    this.pickerSummary = trigger.querySelector('span');
    const viewport = window.visualViewport;
    const fitViewport = () => {
      dialog.style.setProperty('--picker-height', `${viewport?.height ?? window.innerHeight}px`);
      dialog.style.setProperty('--picker-top', `${viewport?.offsetTop ?? 0}px`);
    };
    trigger.addEventListener('click', () => {
      this.query = '';
      this.input.value = '';
      dialog.appendChild(this.wrapper);
      fitViewport();
      viewport?.addEventListener('resize', fitViewport);
      viewport?.addEventListener('scroll', fitViewport);
      dialog.showModal();
      this.show();
    });
    label?.addEventListener('click', event => {
      if (trigger.offsetWidth && !dialog.open) {
        event.preventDefault();
        trigger.click();
      }
    });
    dialog.querySelector('button').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
      if (dialog.open) return;
      viewport?.removeEventListener('resize', fitViewport);
      viewport?.removeEventListener('scroll', fitViewport);
      trigger.before(this.wrapper);
      this.hide();
      this.query = '';
      this.syncInputFromValue();
      if (trigger.offsetWidth) trigger.focus({ preventScroll: true });
    });
  }

  ensureLabelId(label) {
    if (!label.id) label.id = `${this.select.id}-label`;
    return label.id;
  }

  update(items, settings = {}, preferredValue = settings.selectedValue ?? this.select.value) {
    this.settings = { ...this.settings, ...settings };
    if (settings.selectedValue != null) this.query = '';
    const previousValue = String(preferredValue ?? '');
    const fallback = this.settings.fallbackCategory || 'Otros dispositivos';
    const byValue = new Map();
    for (const item of items || []) {
      const value = itemValue(item);
      if (value) byValue.set(value, item);
    }
    this.items = [...byValue.entries()].map(([value, item]) => ({
      value,
      item,
      label: itemLabel(item),
      code: String(item.code ?? item.asset_code ?? '').trim(),
      name: String(item.name ?? item.asset_name ?? 'Dispositivo sin nombre').trim(),
      location: String(item.location_name ?? item.location ?? '').trim(),
      category: categoryName(item, fallback),
      searchText: normalize([
        value, item.id, item.code, item.asset_code, item.name, item.asset_name,
        item.serial, categoryName(item, fallback),
        item.location_name, item.location,
      ].join(' ')),
    }));

    const emptyLabel = this.settings.emptyLabel || this.settings.placeholder || 'Seleccione un dispositivo…';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = emptyLabel;
    this.select.replaceChildren(placeholder);

    const groups = this.groupItems(this.items);
    for (const [category, groupItems] of groups) {
      const optgroup = document.createElement('optgroup');
      optgroup.label = category;
      for (const entry of groupItems) {
        const option = document.createElement('option');
        option.value = entry.value;
        option.textContent = `${entry.label}${entry.location ? ` · ${entry.location}` : ''}`;
        optgroup.appendChild(option);
      }
      this.select.appendChild(optgroup);
    }

    this.select.value = byValue.has(previousValue) ? previousValue : '';
    const selectionRemoved = previousValue !== '' && this.select.value === '';
    if (selectionRemoved) this.query = '';
    this.renderOptions(this.query);
    this.syncInputFromValue();
    this.updateValidity();
    if (selectionRemoved) this.select.dispatchEvent(new Event('change', { bubbles: true }));
  }

  groupItems(items) {
    const groups = new Map();
    for (const entry of items) {
      if (!groups.has(entry.category)) groups.set(entry.category, []);
      groups.get(entry.category).push(entry);
    }
    return [...groups.entries()]
      .map(([category, groupItems]) => [category, groupItems.sort((a, b) =>
        a.code.localeCompare(b.code, 'es', { numeric: true }) || a.name.localeCompare(b.name, 'es')
      )])
      .sort(([a], [b]) => a.localeCompare(b, 'es'));
  }

  handleInput() {
    this.query = this.input.value;
    if (this.select.value && !this.pickerDialog?.open) {
      this.select.value = '';
      this.suppressInputSync = true;
      try {
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
      } finally {
        this.suppressInputSync = false;
      }
    }
    this.updateValidity();
    this.show();
  }

  renderOptions(query = '') {
    const normalized = normalize(query);
    const terms = normalized.split(/\s+/).filter(Boolean);
    const groups = this.groupItems(this.items.filter(entry =>
      !normalized || (this.settings.matches
        ? this.settings.matches(entry.item, normalized)
        : terms.every(term => entry.searchText.includes(term)))
    ));
    this.listbox.replaceChildren();
    this.visibleValues = [];

    if (this.settings.showEmptyOption && !(normalized && (this.pickerDialog?.open || this.settings.showEmptyOption === 'unfiltered'))) {
      const emptyOption = this.createOption('', this.settings.emptyOptionLabel || this.settings.emptyLabel || 'Ninguno / General', '', '');
      this.listbox.appendChild(emptyOption);
      this.visibleValues.push('');
    }

    for (const [category, entries] of groups) {
      const group = document.createElement('div');
      group.className = 'asset-select__group';
      group.setAttribute('role', 'group');
      group.setAttribute('aria-label', category);

      const heading = document.createElement('div');
      heading.className = 'asset-select__group-heading';
      heading.setAttribute('role', 'presentation');
      heading.textContent = `${category} · ${entries.length}`;
      group.appendChild(heading);

      for (const entry of entries) {
        group.appendChild(this.createOption(entry.value, entry.name, entry.code, entry.location));
        this.visibleValues.push(entry.value);
      }
      this.listbox.appendChild(group);
    }

    if (!groups.length) {
      const empty = document.createElement('p');
      empty.className = 'asset-select__no-results';
      empty.setAttribute('role', 'status');
      empty.textContent = this.settings.noResults || (terms.length ? 'No se encontraron dispositivos. Prueba con otro nombre o código.' : 'No hay dispositivos disponibles.');
      this.listbox.appendChild(empty);
    }

    this.activeValue = null;
    this.input.removeAttribute('aria-activedescendant');
    this.markSelectedOption();
  }

  createOption(value, name, code, location) {
    const option = document.createElement('div');
    option.className = 'asset-select__option';
    option.id = `${this.listbox.id}-option-${this.visibleValues.length}`;
    option.dataset.value = value;
    option.setAttribute('role', 'option');
    option.setAttribute('aria-selected', String(value === this.select.value));

    if (!value) {
      option.classList.add('asset-select__option--empty');
      option.textContent = name;
      return option;
    }

    const heading = document.createElement('span');
    heading.className = 'asset-select__option-heading';
    const nameElement = document.createElement('strong');
    nameElement.textContent = name;
    heading.appendChild(nameElement);
    if (code) {
      const codeElement = document.createElement('span');
      codeElement.className = 'asset-select__code';
      codeElement.textContent = code;
      heading.appendChild(codeElement);
    }
    option.appendChild(heading);

    if (location) {
      const detail = document.createElement('span');
      detail.className = 'asset-select__option-detail';
      detail.textContent = `Ubicación: ${location}`;
      option.appendChild(detail);
    }
    return option;
  }

  show() {
    this.renderOptions(this.query);
    this.open = true;
    this.listbox.hidden = false;
    this.input.setAttribute('aria-expanded', 'true');
    if (this.pickerDialog?.open) {
      this.listbox.style.removeProperty('max-height');
      this.listbox.scrollTop = 0;
      return;
    }
    this.fitListbox();
    const panel = this.wrapper.closest('.modal-backdrop > div');
    if (panel) {
      window.addEventListener('resize', this.fitListbox);
      window.visualViewport?.addEventListener('resize', this.fitListbox);
      panel.addEventListener('scroll', this.fitListbox, { passive: true });
    }
  }

  fitListbox() {
    if (!this.open || this.pickerDialog?.open) return;
    const bounds = this.wrapper.getBoundingClientRect();
    if (!bounds.width) {
      this.hide();
      return;
    }
    const viewport = window.visualViewport;
    const top = viewport?.offsetTop ?? 0;
    const height = viewport?.height ?? window.innerHeight;
    const panel = this.wrapper.closest('.modal-backdrop > div')?.getBoundingClientRect();
    const desiredHeight = Math.min(340, height * 0.48);
    const spaceBelow = Math.min(top + height, panel?.bottom ?? Infinity) - bounds.bottom - 10;
    const spaceAbove = bounds.top - Math.max(top, panel?.top ?? 0) - 10;
    const openAbove = spaceBelow < Math.min(220, desiredHeight) && spaceAbove > spaceBelow;
    this.wrapper.classList.toggle('asset-select--above', openAbove);
    this.listbox.style.maxHeight = `${Math.max(0, Math.min(desiredHeight, openAbove ? spaceAbove : spaceBelow))}px`;
  }

  hide() {
    this.open = false;
    window.removeEventListener('resize', this.fitListbox);
    window.visualViewport?.removeEventListener('resize', this.fitListbox);
    this.wrapper.closest('.modal-backdrop > div')?.removeEventListener('scroll', this.fitListbox);
    this.listbox.hidden = true;
    this.input.setAttribute('aria-expanded', 'false');
    this.input.removeAttribute('aria-activedescendant');
    this.activeValue = null;
    this.listbox.querySelectorAll('.is-active').forEach(option => option.classList.remove('is-active'));
  }

  choose(value) {
    const nextValue = String(value ?? '');
    if (nextValue && !this.items.some(entry => entry.value === nextValue)) return;
    this.select.value = nextValue;
    this.query = '';
    this.input.value = this.selectedLabel();
    this.updateValidity();
    this.select.dispatchEvent(new Event('change', { bubbles: true }));
    this.hide();
    if (this.pickerDialog?.open) this.pickerDialog.close();
  }

  handleKeydown(event) {
    if (event.isComposing) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (!this.open) this.show();
      const currentIndex = this.activeValue === null ? -1 : this.visibleValues.indexOf(this.activeValue);
      const direction = event.key === 'ArrowDown' ? 1 : -1;
      const nextIndex = currentIndex < 0
        ? (direction > 0 ? 0 : this.visibleValues.length - 1)
        : Math.max(0, Math.min(this.visibleValues.length - 1, currentIndex + direction));
      if (this.visibleValues.length) this.setActive(this.visibleValues[nextIndex], true);
    } else if (event.key === 'Enter' && this.open) {
      event.preventDefault();
      if (this.activeValue !== null) this.choose(this.activeValue);
      else {
        const matches = this.visibleValues.filter(value => value !== '');
        if (matches.length === 1) this.choose(matches[0]);
      }
    } else if (event.key === 'Escape' && this.open) {
      event.preventDefault();
      if (this.pickerDialog?.open) this.pickerDialog.close();
      else this.hide();
    } else if (event.key === 'Tab' && !this.pickerDialog?.open) {
      this.hide();
    }
  }

  setActive(value, scroll = false) {
    this.activeValue = String(value ?? '');
    const option = [...this.listbox.querySelectorAll('[role="option"]')]
      .find(entry => entry.dataset.value === this.activeValue);
    this.listbox.querySelectorAll('[role="option"]').forEach(entry => entry.classList.toggle('is-active', entry === option));
    if (option) {
      this.input.setAttribute('aria-activedescendant', option.id);
      if (scroll) {
        const bounds = option.getBoundingClientRect();
        const listBounds = this.listbox.getBoundingClientRect();
        const headingHeight = option.parentElement.querySelector('.asset-select__group-heading')?.getBoundingClientRect().height || 0;
        if (bounds.top < listBounds.top + headingHeight) this.listbox.scrollTop += bounds.top - listBounds.top - headingHeight;
        else if (bounds.bottom > listBounds.bottom) this.listbox.scrollTop += bounds.bottom - listBounds.bottom;
      }
    } else {
      this.input.removeAttribute('aria-activedescendant');
    }
  }

  markSelectedOption() {
    this.listbox.querySelectorAll('[role="option"]').forEach(option => {
      option.setAttribute('aria-selected', String(option.dataset.value === this.select.value));
    });
  }

  selectedLabel() {
    const entry = this.items.find(item => item.value === this.select.value);
    return entry ? `${entry.label}${entry.location ? ` · ${entry.location}` : ''}`
      : (this.settings.showEmptyOption ? this.settings.emptyLabel || 'Ninguno / General' : '');
  }

  syncInputFromValue() {
    const selection = this.selectedLabel();
    if (this.pickerSummary) {
      this.pickerSummary.textContent = this.select.value ? selection : 'Buscar o elegir dispositivo';
    }
    this.input.value = this.query || (this.pickerDialog?.open ? '' : selection);
    this.updateValidity();
  }

  updateValidity() {
    const pendingSearch = this.query.trim() !== '';
    const invalid = !this.select.value && (this.originalRequired || pendingSearch);
    this.input.setCustomValidity(invalid
      ? this.settings.invalidSelection || (pendingSearch && !this.originalRequired ? 'Selecciona un dispositivo de la lista o borra la búsqueda para continuar sin dispositivo.' : 'Selecciona un dispositivo de la lista.')
      : '');
    if (!invalid) this.input.removeAttribute('aria-invalid');
  }
}
