/**
 * theme.js — GoZayan Dark / Light Mode Toggle
 * Reads and saves preference to localStorage key: 'gz_theme'
 * Values: 'dark' | 'light'
 * Applied immediately (before page paint) to avoid flash.
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'gz_theme';
    const DARK_CLASS  = 'dark-mode';

    // ── Apply saved theme immediately (no flash) ──────────────
    function applyTheme(theme) {
        if (theme === 'dark') {
            document.body.classList.add(DARK_CLASS);
        } else {
            document.body.classList.remove(DARK_CLASS);
        }
    }

    // ── Get current preference ────────────────────────────────
    function getSaved() {
        return localStorage.getItem(STORAGE_KEY) || 'light';
    }

    // ── Toggle ────────────────────────────────────────────────
    function toggleTheme() {
        const current = getSaved();
        const next    = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem(STORAGE_KEY, next);
        applyTheme(next);
        updateAllButtons(next);
    }

    // ── Update button icons/labels across the page ────────────
    function updateAllButtons(theme) {
        document.querySelectorAll('.gz-theme-btn').forEach(btn => {
            const icon  = btn.querySelector('.gz-theme-icon');
            const label = btn.querySelector('.gz-theme-label');
            if (icon)  icon.textContent  = theme === 'dark' ? '☀️' : '🌙';
            if (label) label.textContent = theme === 'dark' ? 'Light' : 'Dark';
            btn.setAttribute('title', theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode');
            btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode');
        });
    }

    // ── Inject CSS to avoid FOUC — apply before body renders ──
    // This runs as soon as the script is parsed.
    const saved = getSaved();

    // We use a <style> injection trick to apply dark-mode class
    // before CSS is fully parsed, preventing white flash.
    if (saved === 'dark') {
        // Synchronously add class if body already exists, else observe
        if (document.body) {
            document.body.classList.add(DARK_CLASS);
        } else {
            // Script in <head> — wait for body
            document.documentElement.classList.add('pre-dark');
        }
    }

    // ── DOMContentLoaded: wire up buttons ─────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // If we added pre-dark to html, move to body now
        if (document.documentElement.classList.contains('pre-dark')) {
            document.documentElement.classList.remove('pre-dark');
            document.body.classList.add(DARK_CLASS);
        }

        // Apply theme properly
        applyTheme(getSaved());

        // Wire up all toggle buttons
        document.querySelectorAll('.gz-theme-btn').forEach(btn => {
            btn.addEventListener('click', toggleTheme);
        });

        // Update icons
        updateAllButtons(getSaved());
    });

    // ── Expose globally for manual use ────────────────────────
    window.gzTheme = {
        toggle: toggleTheme,
        set: function(theme) {
            localStorage.setItem(STORAGE_KEY, theme);
            applyTheme(theme);
            updateAllButtons(theme);
        },
        get: getSaved,
    };
})();
