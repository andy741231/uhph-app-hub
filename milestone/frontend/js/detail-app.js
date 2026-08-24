/**
 * Detail Application for Record Detail View
 */

import { ApiService } from './api-service.js';
import { RecordManager } from './record-manager.js';
import { BannerManager } from './banner-manager.js';
import { Utils } from './utils.js';

const { reactive } = Vue;

export class DetailApp {
  constructor() {
    this.apiService = new ApiService();
    this.recordManager = new RecordManager(this.apiService);
    this.bannerManager = new BannerManager(this.apiService);

    const params = this.parseUrlParams();

    this.state = reactive({
      // Record data
      record: null,
      tableSchema: null,
      baseId: params.baseId,
      table: params.table,
      id: params.id,
      loading: true,
      error: '',
      primaryFields: [],
      additionalFields: [],
      primaryTitle: 'Record Detail',
      deliveredOn: '',
      pdfError: '',
      isExportingPdf: false,

      // Banner state
      bannerDetails: {
        logoUrl: '',
        title: '',
        subtitle: '',
        description: ''
      },
      bannerError: '',
      bannerRecordId: this.bannerManager.recordId,
      bannerEditor: this.bannerManager.editor,

      // Record editor
      recordEditor: this.recordManager.editor
    });
  }

  /**
   * Parse URL parameters
   */
  parseUrlParams() {
    const params = new URLSearchParams(window.location.search);
    return {
      baseId: params.get('baseId') || '',
      table: params.get('table') || '',
      id: params.get('id') || ''
    };
  }

  /**
   * Initialize the application
   */
  async init() {
    if (!this.state.baseId || !this.state.table || !this.state.id) {
      this.state.error = 'Missing required parameters in URL';
      this.state.loading = false;
      return;
    }

    try {
      await Promise.all([
        this.loadRecord(),
        this.loadBanner()
      ]);
    } catch (error) {
      this.state.error = error.message;
    } finally {
      this.state.loading = false;
    }
  }

  /**
   * Load record data
   */
  async loadRecord() {
    try {
      const [tableResponse, record] = await Promise.all([
        this.apiService.listTables(this.state.baseId),
        this.recordManager.getRecord(this.state.baseId, this.state.table, this.state.id)
      ]);

      const tables = tableResponse.tables || [];
      const targetName = this.state.table.toLowerCase();
      this.state.tableSchema = tables.find(table => (table.name || '').toLowerCase() === targetName);
      
      this.state.record = record;
      this.processRecordData();
    } catch (error) {
      throw new Error(`Failed to load record: ${error.message}`);
    }
  }

  /**
   * Process record data for display
   */
  processRecordData() {
    if (!this.state.record) return;

    const { primaryFields, additionalFields } = this.recordManager.processRecordFields(
      this.state.record,
      ['Date', 'Milestone Type', 'Deliverable'],
      this.state.tableSchema
    );
    this.state.primaryFields = primaryFields;
    this.state.additionalFields = additionalFields;

    // Set primary title
    const titleField = primaryFields.find(f => f.key === 'Deliverable');
    if (titleField && titleField.value) {
      this.state.primaryTitle = titleField.value;
    }

    // Set delivered date
    const dateField = primaryFields.find(f => f.key === 'Date');
    if (dateField && dateField.value) {
      this.state.deliveredOn = Utils.formatDate(dateField.value);
    }
  }

  /**
   * Load banner content
   */
  async loadBanner() {
    try {
      this.state.bannerDetails = await this.bannerManager.fetchBanner(this.state.baseId);
      this.state.bannerError = '';
      this.state.bannerRecordId = this.bannerManager.recordId;
    } catch (error) {
      this.state.bannerError = error.message;
    }
  }

  /**
   * Check if banner has content
   */
  get hasBannerContent() {
    const details = this.state.bannerDetails || {};
    return Boolean(details.logoUrl || details.title || details.subtitle || details.description);
  }

  /**
   * Check if table is Milestone Activities
   */
  get isMilestoneActivities() {
    const normalized = (this.state.table || '').trim().toLowerCase();
    if (!normalized) return false;
    if (normalized === 'milestone activities') return true;
    return normalized.replace(/\s+/g, '') === 'milestoneactivities';
  }

  /**
   * Check if table is Banner
   */
  get isBanner() {
    const normalized = (this.state.table || '').trim().toLowerCase();
    if (!normalized) return false;
    if (normalized === 'banner' || normalized === 'banners') return true;
    return normalized.includes('banner');
  }

  /**
   * Format date for display
   */
  formatDate(dateString) {
    return Utils.formatDate(dateString);
  }

  /**
   * Export to PDF
   */
  async exportPdf() {
    if (this.state.isExportingPdf || !this.state.record) return;

    this.state.pdfError = '';
    this.state.isExportingPdf = true;

    try {
      // Add PDF export classes for styling
      document.body.classList.add('pdf-exporting');
      const pageElement = document.querySelector('.page');
      if (pageElement) {
        pageElement.classList.add('pdf-exporting');
        if (this.state.primaryFields.length || this.state.additionalFields.length) {
          pageElement.classList.add('pdf-has-content');
        } else {
          pageElement.classList.add('pdf-cover-only');
        }
      }

      // Use html2pdf for detail page export
      if (typeof html2pdf !== 'undefined') {
        const element = document.querySelector('.page');
        const opt = {
          margin: 0.5,
          filename: `${this.state.primaryTitle.replace(/\s+/g, '_').toLowerCase()}_${new Date().toISOString().slice(0, 10)}.pdf`,
          image: { type: 'jpeg', quality: 0.98 },
          html2canvas: { scale: 2, useCORS: true },
          jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        await html2pdf().set(opt).from(element).save();
      } else {
        throw new Error('PDF export library not available');
      }
    } catch (error) {
      console.error('Failed to export PDF', error);
      this.state.pdfError = 'Failed to export PDF. Please try again.';
    } finally {
      this.state.isExportingPdf = false;
      
      // Remove PDF export classes
      document.body.classList.remove('pdf-exporting');
      const pageElement = document.querySelector('.page');
      if (pageElement) {
        pageElement.classList.remove('pdf-exporting', 'pdf-has-content', 'pdf-cover-only');
      }
    }
  }

  /**
   * Export to Word document
   */
  exportWord() {
    // Simple Word export using HTML
    const content = this.generateWordContent();
    const blob = new Blob([content], { type: 'application/msword' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${this.state.primaryTitle.replace(/\s+/g, '_').toLowerCase()}_${new Date().toISOString().slice(0, 10)}.doc`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  /**
   * Generate Word document content
   */
  generateWordContent() {
    let html = `
      <html>
        <head>
          <meta charset="utf-8">
          <title>${this.state.primaryTitle}</title>
        </head>
        <body>
          <h1>${this.state.primaryTitle}</h1>
    `;

    if (this.state.primaryFields.length) {
      html += '<h2>Overview</h2>';
      this.state.primaryFields.forEach(field => {
        html += `<p><strong>${field.key}:</strong> ${field.value || 'Not provided'}</p>`;
      });
    }

    if (this.state.additionalFields.length) {
      html += '<h2>Additional Details</h2>';
      this.state.additionalFields.forEach(field => {
        html += `<p><strong>${field.key}:</strong> ${field.value || 'Not provided'}</p>`;
      });
    }

    html += '</body></html>';
    return html;
  }

  /**
   * Go back to dashboard
   */
  backToList() {
    const params = new URLSearchParams({ baseId: this.state.baseId, table: this.state.table });
    window.location.href = `index.html?${params.toString()}`;
  }

  /**
   * Open record editor
   */
  async openRecordEditor() {
    await this.recordManager.openEditor(this.state.baseId, this.state.table, this.state.id);
  }

  /**
   * Close record editor
   */
  closeRecordEditor() {
    this.recordManager.closeEditor();
  }

  /**
   * Submit record editor
   */
  async submitRecordEditor() {
    try {
      const updatedRecord = await this.recordManager.submitEditor(this.state.baseId, this.state.table, this.state.id);
      if (updatedRecord) {
        this.state.record = updatedRecord;
        this.processRecordData();
        this.closeRecordEditor();
      }
    } catch (error) {
      // Error is handled in record manager
    }
  }

  /**
   * Open banner editor
   */
  async openBannerEditor() {
    await this.bannerManager.openEditor(this.state.baseId);
  }

  /**
   * Close banner editor
   */
  closeBannerEditor() {
    this.bannerManager.closeEditor();
  }

  /**
   * Submit banner editor
   */
  async submitBannerEditor() {
    try {
      const updatedDetails = await this.bannerManager.submitEditor(this.state.baseId);
      if (updatedDetails) {
        this.state.bannerDetails = updatedDetails;
        this.state.bannerRecordId = this.bannerManager.recordId;
        this.closeBannerEditor();
      }
    } catch (error) {
      // Error is handled in banner manager
    }
  }

  /**
   * Display field value for readonly fields
   */
  displayFieldValue(fieldConfig) {
    return this.recordManager.displayFieldValue(fieldConfig);
  }

  /**
   * Get Vue.js reactive data
   */
  getVueData() {
    return this.state;
  }

  /**
   * Get Vue.js computed properties
   */
  getVueComputed() {
    return {
      hasBannerContent: () => this.hasBannerContent,
      isMilestoneActivities: () => this.isMilestoneActivities,
      isBanner: () => this.isBanner,
      normalizedTable: () => (this.state.table || '').trim().toLowerCase()
    };
  }

  /**
   * Get Vue.js methods
   */
  getVueMethods() {
    return {
      // Navigation
      backToList: () => this.backToList(),
      
      // Export
      exportPdf: () => this.exportPdf(),
      exportWord: () => this.exportWord(),
      
      // Record editor
      openRecordEditor: () => this.openRecordEditor(),
      closeRecordEditor: () => this.closeRecordEditor(),
      submitRecordEditor: () => this.submitRecordEditor(),
      
      // Banner editor
      openBannerEditor: () => this.openBannerEditor(),
      closeBannerEditor: () => this.closeBannerEditor(),
      submitBannerEditor: () => this.submitBannerEditor(),
      
      // Utilities
      formatDate: (date) => this.formatDate(date),
      displayFieldValue: (fieldConfig) => this.displayFieldValue(fieldConfig)
    };
  }
}
