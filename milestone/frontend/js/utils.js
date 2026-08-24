/**
 * Utility functions for common operations
 */

export const Utils = {
  /**
   * Normalize value for display
   */
  normalizeValue(value) {
    if (value === null || value === undefined) return '';
    if (Array.isArray(value)) {
      return value.map(v => this.normalizeValue(v)).join(', ');
    }
    if (typeof value === 'object') {
      // Special handling for Collaborator field
      if (value.name && value.email) {
        return `${value.name} - ${value.email}`;
      }
      if (value.url) {
        return value.url;
      }
      return JSON.stringify(value, null, 2);
    }
    return String(value);
  },

  /**
   * Parse date filter value
   */
  parseDateFilter(value, endOfDay = false) {
    if (!value) return null;
    const parts = String(value).split('-').map(segment => Number(segment));
    if (parts.length !== 3 || parts.some(segment => Number.isNaN(segment))) return null;
    const [year, month, day] = parts;
    if (!year || !month || !day) return null;
    const date = new Date(Date.UTC(year, month - 1, day, endOfDay ? 23 : 0, endOfDay ? 59 : 0, endOfDay ? 59 : 0, endOfDay ? 999 : 0));
    const timestamp = date.getTime();
    return Number.isNaN(timestamp) ? null : timestamp;
  },

  /**
   * Normalize date for sorting
   */
  normalizeDateForSort(value) {
    if (value == null) return null;
    if (Array.isArray(value)) {
      for (const entry of value) {
        const parsed = this.normalizeDateForSort(entry);
        if (parsed != null) return parsed;
      }
      return null;
    }
    const date = new Date(value);
    const timestamp = date.getTime();
    return Number.isNaN(timestamp) ? null : timestamp;
  },

  /**
   * Normalize sort value
   */
  normalizeSortValue(value) {
    if (value == null) return '';
    if (Array.isArray(value)) {
      for (const entry of value) {
        const normalized = this.normalizeValue(entry).trim();
        if (normalized) return normalized;
      }
      return '';
    }
    const normalized = this.normalizeValue(value);
    return normalized ? normalized.trim() : '';
  },

  /**
   * Format date for display
   */
  formatDate(dateString) {
    if (!dateString) return '';
    try {
      return new Date(dateString).toLocaleDateString();
    } catch (error) {
      return dateString;
    }
  },

  /**
   * Get field value from fields object using candidate names
   */
  getFieldValue(fields, candidates = []) {
    if (!fields) return undefined;
    const normalized = {};
    Object.entries(fields).forEach(([key, value]) => {
      const norm = key.replace(/[\s_-]+/g, '').toLowerCase();
      if (!(norm in normalized)) {
        normalized[norm] = value;
      }
    });
    for (const candidate of candidates) {
      const norm = String(candidate).replace(/[\s_-]+/g, '').toLowerCase();
      if (norm in normalized) {
        return normalized[norm];
      }
    }
    return undefined;
  },

  /**
   * Coalesce field value from multiple candidates
   */
  coalesceField(fields, candidates = []) {
    for (const key of candidates) {
      if (Object.prototype.hasOwnProperty.call(fields, key) && fields[key] != null && fields[key] !== '') {
        return fields[key];
      }
    }
    return undefined;
  },

  /**
   * Parse numeric value
   */
  parseNumeric(value) {
    if (value == null) return null;
    if (typeof value === 'number') {
      return Number.isFinite(value) ? value : null;
    }
    const cleaned = String(value).replace(/[^0-9.+-]/g, '');
    if (!cleaned.trim()) return null;
    const num = Number(cleaned);
    return Number.isFinite(num) ? num : null;
  },

  /**
   * Format metric value
   */
  formatMetricValue(value) {
    if (typeof value === 'number') {
      try {
        return value.toLocaleString();
      } catch (error) {
        return String(value);
      }
    }
    return value;
  },

  /**
   * Clone value (deep copy for arrays and objects)
   */
  cloneValue(value) {
    if (Array.isArray(value)) {
      return value.map(entry => (typeof entry === 'object' && entry !== null) ? { ...entry } : entry);
    }
    if (value && typeof value === 'object') {
      return { ...value };
    }
    return value;
  },

  /**
   * Debounce function
   */
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  /**
   * Show notification
   */
  showNotification(message, type = 'info', duration = 3000) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Style the notification
    Object.assign(notification.style, {
      position: 'fixed',
      top: '20px',
      right: '20px',
      padding: '12px 20px',
      borderRadius: '8px',
      color: 'white',
      fontWeight: '500',
      zIndex: '9999',
      transform: 'translateX(100%)',
      transition: 'transform 0.3s ease-in-out',
      backgroundColor: type === 'error' ? '#dc2626' : type === 'success' ? '#16a34a' : '#2563eb'
    });
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
      notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Animate out and remove
    setTimeout(() => {
      notification.style.transform = 'translateX(100%)';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 300);
    }, duration);
  }
};
