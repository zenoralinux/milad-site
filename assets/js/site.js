(() => {
  "use strict";
  const root = document.documentElement;
  const themeToggle = document.getElementById("themeToggle");
  const menuToggle = document.getElementById("menuToggle");
  const primaryNav = document.getElementById("primaryNav");
  const commandDialog = document.getElementById("commandDialog");
  const commandInput = document.getElementById("commandInput");
  const searchTrigger = document.getElementById("searchTrigger");
  const commandClose = document.getElementById("commandClose");
  const projectGrid = document.getElementById("projectsGrid");
  const projectCount = document.getElementById("projectCount");
  const emptyFilter = document.getElementById("emptyFilter");
  const progressBar = document.getElementById("readingProgress");
  const safeStorage = {
    get(key) { try { return window.localStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { window.localStorage.setItem(key, value); } catch { /* Storage may be unavailable. */ } }
  };
  const numberFa = new Intl.NumberFormat("fa-IR");
  const projectCards = projectGrid ? Array.from(projectGrid.querySelectorAll(".project-card")) : [];
  const filterButtons = Array.from(document.querySelectorAll("[data-filter]"));
  const commandLinks = Array.from(document.querySelectorAll(".command-link"));

  function applyTheme(theme) {
    const isLight = theme === "light";
    root.dataset.theme = isLight ? "light" : "dark";
    if (themeToggle) {
      themeToggle.setAttribute("aria-pressed", String(isLight));
      themeToggle.setAttribute("aria-label", isLight ? "فعال‌کردن پوسته تاریک" : "فعال‌کردن پوسته روشن");
    }
    const themeColor = document.querySelector('meta[name="theme-color"]');
    if (themeColor) themeColor.setAttribute("content", isLight ? "#f4f5f8" : "#080b12");
  }
  const savedTheme = safeStorage.get("milad-theme");
  const systemPrefersLight = window.matchMedia && window.matchMedia("(prefers-color-scheme: light)").matches;
  applyTheme(savedTheme || (systemPrefersLight ? "light" : "dark"));
  themeToggle?.addEventListener("click", () => {
    const nextTheme = root.dataset.theme === "light" ? "dark" : "light";
    applyTheme(nextTheme);
    safeStorage.set("milad-theme", nextTheme);
  });

  function closeMenu() {
    if (!menuToggle || !primaryNav) return;
    menuToggle.classList.remove("is-open");
    primaryNav.classList.remove("is-open");
    menuToggle.setAttribute("aria-expanded", "false");
    menuToggle.setAttribute("aria-label", "باز کردن منو");
  }
  menuToggle?.addEventListener("click", () => {
    const isOpen = primaryNav.classList.toggle("is-open");
    menuToggle.classList.toggle("is-open", isOpen);
    menuToggle.setAttribute("aria-expanded", String(isOpen));
    menuToggle.setAttribute("aria-label", isOpen ? "بستن منو" : "باز کردن منو");
  });
  primaryNav?.querySelectorAll("a").forEach(link => link.addEventListener("click", closeMenu));

  function openCommandPalette() {
    if (!commandDialog || typeof commandDialog.showModal !== "function") {
      document.getElementById("work")?.scrollIntoView({ behavior: "smooth" });
      return;
    }
    if (!commandDialog.open) commandDialog.showModal();
    window.setTimeout(() => commandInput?.focus(), 40);
    closeMenu();
  }
  function filterCommandLinks(query) {
    const normalized = query.trim().toLocaleLowerCase("fa");
    let firstVisible = null;
    commandLinks.forEach(link => {
      const searchable = (link.dataset.commandSearch || link.textContent).toLocaleLowerCase("fa");
      const visible = !normalized || searchable.includes(normalized);
      link.hidden = !visible;
      if (visible && !firstVisible) firstVisible = link;
    });
    return firstVisible;
  }
  function closeCommandPalette() {
    if (commandDialog?.open) commandDialog.close();
    if (commandInput) {
      commandInput.value = "";
      filterCommandLinks("");
    }
  }
  searchTrigger?.addEventListener("click", openCommandPalette);
  commandClose?.addEventListener("click", closeCommandPalette);
  commandInput?.addEventListener("input", () => filterCommandLinks(commandInput.value));
  commandLinks.forEach(link => link.addEventListener("click", closeCommandPalette));
  commandDialog?.addEventListener("click", event => {
    if (event.target === commandDialog) closeCommandPalette();
  });

  document.addEventListener("keydown", event => {
    const target = event.target;
    const isTyping = target instanceof HTMLElement &&
      (target.isContentEditable || ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName));
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
      event.preventDefault();
      openCommandPalette();
      return;
    }
    if (event.key === "/" && !isTyping && !commandDialog?.open) {
      event.preventDefault();
      openCommandPalette();
      return;
    }
    if (event.key === "Escape") closeMenu();
  });

  function applyProjectFilter(filter) {
    let visibleCount = 0;
    projectCards.forEach(card => {
      const visible = filter === "all" || card.dataset.category === filter;
      card.hidden = !visible;
      if (visible) visibleCount += 1;
    });
    filterButtons.forEach(button => {
      const active = button.dataset.filter === filter;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-pressed", String(active));
    });
    if (projectCount) projectCount.textContent = numberFa.format(visibleCount) + " پروژه";
    if (emptyFilter) emptyFilter.hidden = visibleCount !== 0;
  }
  filterButtons.forEach(button => button.addEventListener("click", () => applyProjectFilter(button.dataset.filter || "all")));

  const yearNode = document.getElementById("currentYear");
  if (yearNode) yearNode.textContent = new Intl.DateTimeFormat("fa-IR", { year: "numeric" }).format(new Date());

  function updateReadingProgress() {
    if (!progressBar) return;
    const scrollable = document.documentElement.scrollHeight - window.innerHeight;
    const ratio = scrollable > 0 ? Math.min(1, Math.max(0, window.scrollY / scrollable)) : 0;
    progressBar.style.width = String(ratio * 100) + "%";
  }
  window.addEventListener("scroll", updateReadingProgress, { passive: true });
  window.addEventListener("resize", updateReadingProgress, { passive: true });
  updateReadingProgress();

  const reduceMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  if (!reduceMotion && "IntersectionObserver" in window) {
    root.classList.add("has-motion");
    const observer = new IntersectionObserver((entries, instance) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          instance.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -24px 0px" });
    document.querySelectorAll(".reveal").forEach(element => observer.observe(element));
  }

  const portrait = document.querySelector(".portrait-frame img");
  portrait?.addEventListener("error", () => {
    portrait.hidden = true;
    portrait.parentElement?.classList.add("is-fallback");
  }, { once: true });
})();
