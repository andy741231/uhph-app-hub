/**
 * PDF Service for generating PDF exports
 */

import { Utils } from './utils.js';

export class PdfService {
  constructor() {
    // Palette controls the brand colors used throughout every PDF page (cover, tables, cards).
    this.palette = {
      text: [15, 23, 42],
      muted: [71, 85, 105],
      border: [226, 232, 240],
      surface: [255, 255, 255],
      surfaceAlt: [241, 245, 249],
      accent: [37, 99, 235],
      background: [248, 250, 252],
      cardBackground: [255, 255, 255]
    };
  }

  /**
   * Convert URL to data URL
   */
  async toDataUrl(url) {
    if (!url) return null;
    try {
      const response = await fetch(url, { mode: 'cors' });
      if (!response.ok) return null;
      const blob = await response.blob();
      return await new Promise(resolve => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(reader.result);
        reader.onerror = () => resolve(null);
        reader.readAsDataURL(blob);
      });
    } catch (error) {
      return null;
    }
  }

  /**
   * Get image dimensions
   */
  getImageDimensions(src) {
    return new Promise(resolve => {
      if (!src) return resolve(null);
      const img = new Image();
      img.onload = () => resolve({ width: img.width, height: img.height });
      img.onerror = () => resolve(null);
      img.src = src;
    });
  }

  /**
   * Convert pixels to inches
   */
  pxToIn(pixels) {
    return pixels / 96;
  }

  /**
   * Render cover card using html2canvas
   */
  async renderCoverCard({ title, subtitle, description, logoDataUrl }) {
    if (typeof window.html2canvas === 'undefined') return null;

    // Hidden sandbox container defines the overall cover card background before it is rasterized.
    const sandbox = document.createElement('div');
    sandbox.style.cssText = `
      position: fixed;
      left: 0;
      top: -9999px;
      width: 860px;
      padding: 60px 0;
      background: #f8fafc;
      display: flex;
      justify-content: center;
      font-family: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
    `;

    // `card` is the white rounded rectangle that becomes the exported cover page image.
    const card = document.createElement('div');
    card.style.cssText = `
      width: 640px;
      max-width: 640px;
      background: #ffffff;
      border-radius: 24px;
      padding: 80px 72px;
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 32px;
      text-align: center;
      color: rgb(15, 23, 42);
    `;

    // Create content
    // `headerGroup` centers the logo and text stack used on the cover page.
    const headerGroup = document.createElement('div');
    headerGroup.style.cssText = `
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 24px;
    `;

    if (logoDataUrl) {
      // Logo wrapper controls the badge around the logo image on the cover.
      const logoWrap = document.createElement('div');
      logoWrap.style.cssText = `
        width: 150px;
        height: 150px;
        border-radius: 32px;
        background: rgba(241, 245, 249, 1);
        border: 1px solid rgba(226, 232, 240, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        padding: 18px;
      `;

      // Actual logo image sizing.
      const img = document.createElement('img');
      img.src = logoDataUrl;
      img.alt = title || 'Banner logo';
      img.style.cssText = `
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
      `;

      logoWrap.appendChild(img);
      headerGroup.appendChild(logoWrap);
    }

    // Text block wraps title, subtitle, and description on the cover page.
    const textBlock = document.createElement('div');
    textBlock.style.cssText = `
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
    `;

    // `titleEl` controls the large uppercase heading on the cover page.
    const titleEl = document.createElement('h1');
    titleEl.textContent = title || 'Airtable Manager';
    titleEl.style.cssText = `
      margin: 0;
      font-size: 36px;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: rgb(15, 23, 42);
    `;
    textBlock.appendChild(titleEl);

    if (subtitle) {
      // Subtitle text styling beneath the main title.
      const subtitleEl = document.createElement('p');
      subtitleEl.textContent = subtitle;
      subtitleEl.style.cssText = `
        margin: 0;
        font-size: 20px;
        font-weight: 500;
        color: rgb(71, 85, 105);
      `;
      textBlock.appendChild(subtitleEl);
    }

    const desc = description || 'Manage Airtable data with streamlined connections, quick insights, and actionable tools.';
    if (desc) {
      // Paragraph copy on the cover page.
      const descEl = document.createElement('p');
      descEl.textContent = desc;
      descEl.style.cssText = `
        margin: 0;
        max-width: 520px;
        font-size: 15px;
        line-height: 1.65;
        color: rgb(71, 85, 105);
        white-space: pre-wrap;
      `;
      textBlock.appendChild(descEl);
    }

    headerGroup.appendChild(textBlock);
    card.appendChild(headerGroup);
    sandbox.appendChild(card);
    document.body.appendChild(sandbox);

    try {
      const canvas = await window.html2canvas(card, {
        backgroundColor: null,
        scale: 2
      });
      return canvas.toDataURL('image/png', 1.0);
    } catch (error) {
      console.error('Failed to render cover card', error);
      return null;
    } finally {
      sandbox.remove();
    }
  }

  /**
   * Export records to PDF
   */
  async exportToPdf(records, bannerDetails = {}, tableSchema = null) {
    const jsPDFLib = window.jspdf && window.jspdf.jsPDF;
    if (!jsPDFLib) {
      throw new Error('PDF generator is unavailable. Please refresh and try again.');
    }

    if (!records || !records.length) {
      throw new Error('No records available to export.');
    }

    try {
      // `doc` defines the base PDF document configuration (letter portrait with inch units).
      const doc = new jsPDFLib({ unit: 'in', format: 'letter', orientation: 'portrait' });
      const setTextColor = rgb => doc.setTextColor(rgb[0], rgb[1], rgb[2]);
      const setFillColor = rgb => doc.setFillColor(rgb[0], rgb[1], rgb[2]);
      const setDrawColor = rgb => doc.setDrawColor(rgb[0], rgb[1], rgb[2]);
      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      const marginX = 0.85;
      const marginY = 1;

      const coverTitle = bannerDetails.title || 'Airtable Manager';
      const coverSubtitle = bannerDetails.subtitle || '';
      const coverDescription = bannerDetails.description || 'Manage Airtable data with streamlined connections, quick insights, and actionable tools.';

      // Create cover page background fill.
      setFillColor(this.palette.surface);
      doc.rect(0, 0, pageWidth, pageHeight, 'F');

      const logoDataUrl = await this.toDataUrl(bannerDetails.logoUrl);
      let coverRendered = false;
      const coverImageUrl = await this.renderCoverCard({
        title: coverTitle,
        subtitle: coverSubtitle,
        description: coverDescription,
        logoDataUrl
      });

      if (coverImageUrl) {
        // Add the high-fidelity HTML-rendered cover card.
        const dims = await this.getImageDimensions(coverImageUrl);
        if (dims && dims.width && dims.height) {
          const availableWidth = pageWidth - marginX * 2;
          const aspect = dims.height / dims.width;
          const imageWidth = availableWidth;
          const imageHeight = imageWidth * aspect;
          const offsetY = Math.max((pageHeight - imageHeight) / 2, marginY * 0.6);
          doc.addImage(coverImageUrl, 'PNG', marginX, offsetY, imageWidth, imageHeight, undefined, 'FAST');
          coverRendered = true;
        }
      }

      if (!coverRendered) {
        // Fallback draws a simplified cover layout directly with jsPDF primitives.
        this.renderFallbackCover(doc, pageWidth, pageHeight, marginX, marginY, coverTitle, coverSubtitle, coverDescription, logoDataUrl);
      }

      setTextColor(this.palette.text);

      // Start content on new page
      doc.addPage();
      this.renderTableLayout(doc, records, pageWidth, pageHeight, marginX, marginY);

      const filename = `${(coverTitle || 'records').trim().replace(/\s+/g, '_').toLowerCase()}_${new Date().toISOString().slice(0, 10)}.pdf`;
      doc.save(filename);
    } catch (error) {
      console.error('Failed to generate PDF', error);
      throw new Error('Unable to generate PDF. Please try again later.');
    }
  }

  /**
   * Render fallback cover when canvas rendering fails
   */
  renderFallbackCover(doc, pageWidth, pageHeight, marginX, marginY, title, subtitle, description, logoDataUrl) {
    let currentY = marginY + 0.4;

    if (logoDataUrl) {
      // Top logo placement on fallback cover page.
      const logoWidth = Math.min(3.5, pageWidth - marginX * 2);
      const logoHeight = logoWidth * 0.6;
      const logoX = (pageWidth - logoWidth) / 2;
      doc.addImage(logoDataUrl, 'PNG', logoX, currentY, logoWidth, logoHeight, undefined, 'FAST');
      currentY += logoHeight + 0.9;
    } else {
      currentY += 0.8;
    }

    const coverLines = [];
    if (title) {
      // Uppercase fallback title formatting.
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(28);
      coverLines.push({ text: title.toUpperCase(), size: 28, weight: 'bold', color: this.palette.text });
    }
    if (subtitle) {
      // Subtitle text beneath the title on fallback cover.
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(18);
      coverLines.push({ text: subtitle, size: 18, weight: 'normal', color: this.palette.muted });
    }
    if (description) {
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(12);
      const wrapped = doc.splitTextToSize(description, pageWidth - marginX * 2);
      wrapped.forEach(line => coverLines.push({ text: line, size: 12, weight: 'normal', color: this.palette.muted }));
    }

    const totalLineHeight = coverLines.reduce((sum, line) => sum + (line.size / 72 * 1.4), 0);
    const startY = Math.max((pageHeight - totalLineHeight) / 2, currentY);
    let lineY = startY;
    coverLines.forEach(line => {
      doc.setFont('helvetica', line.weight === 'bold' ? 'bold' : 'normal');
      doc.setFontSize(line.size);
      doc.setTextColor(line.color[0], line.color[1], line.color[2]);
      doc.text(line.text, pageWidth / 2, lineY, { align: 'center' });
      lineY += (line.size / 72) * 1.4;
    });
  }

  /**
   * Render table layout for records
   */
  renderTableLayout(doc, records, pageWidth, pageHeight, marginX, marginY) {
    const setFillColor = rgb => doc.setFillColor(rgb[0], rgb[1], rgb[2]);
    const setDrawColor = rgb => doc.setDrawColor(rgb[0], rgb[1], rgb[2]);
    const setTextColor = rgb => doc.setTextColor(rgb[0], rgb[1], rgb[2]);

    const contentWidth = pageWidth - marginX * 2;
    const col1Width = contentWidth * 0.4; // 40% for Milestone
    const col2Width = contentWidth * 0.6; // 60% for Deliverables
    const cellPadding = this.pxToIn(7);
    
    let currentY = marginY;
    const bottomLimit = pageHeight - marginY;

    // Define header function
    const drawHeader = (y) => {
      setDrawColor([0, 0, 0]); // Black border
      doc.setLineWidth(this.pxToIn(1)); // 1px border
      setFillColor([106, 168, 79]); // Green header background
      
      // Header cells
      doc.rect(marginX, y, col1Width, 0.4, 'FD');
      doc.rect(marginX + col1Width, y, col2Width, 0.4, 'FD');
      
      setTextColor([255, 255, 255]); // White text
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(12);
      
      // Vertical center text roughly
      doc.text("Milestone", marginX + cellPadding, y + 0.26);
      doc.text("Deliverables", marginX + col1Width + cellPadding, y + 0.26);
      
      return y + 0.4;
    };

    // Initial header
    currentY = drawHeader(currentY);

    // Set styles for content
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    
    records.forEach(rec => {
      const fields = rec.fields || {};
      
      // Helper to get field value case-insensitively
      const getFieldValue = (name) => {
        const key = Object.keys(fields).find(k => k.toLowerCase() === name.toLowerCase());
        return key ? fields[key] : '';
      };

      const milestoneText = Utils.normalizeValue(getFieldValue('Milestone Type'));
      const deliverableText = Utils.normalizeValue(getFieldValue('Deliverable')); // Matches user sort option

      // Wrap text
      const milestoneLines = doc.splitTextToSize(milestoneText, col1Width - (cellPadding * 2));
      const deliverableLines = doc.splitTextToSize(deliverableText, col2Width - (cellPadding * 2));
      
      const lineHeight = 0.2; // Approx line height for size 10
      // Calculate height needed
      const h1 = milestoneLines.length * lineHeight + (cellPadding * 2);
      const h2 = deliverableLines.length * lineHeight + (cellPadding * 2);
      // Minimum row height
      const rowHeight = Math.max(h1, h2, 0.3); 

      // Check page break
      if (currentY + rowHeight > bottomLimit) {
        doc.addPage();
        currentY = marginY;
        currentY = drawHeader(currentY);
        
        // Reset styles
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
      }

      // Draw row
      setDrawColor([0, 0, 0]); // Black borders
      doc.setLineWidth(this.pxToIn(1)); // 1px border
      setTextColor([68, 114, 196]); // Blue text
      
      // Cell 1 rect
      doc.rect(marginX, currentY, col1Width, rowHeight, 'S');
      // Cell 2 rect
      doc.rect(marginX + col1Width, currentY, col2Width, rowHeight, 'S');
      
      // Text
      // Use textBaseline top-ish logic or just padding
      doc.text(milestoneLines, marginX + cellPadding, currentY + cellPadding + 0.1);
      doc.text(deliverableLines, marginX + col1Width + cellPadding, currentY + cellPadding + 0.1);

      currentY += rowHeight;
    });
  }

  /**
   * Render a single record page
   */
  async renderRecordPage(doc, fields, rec, index, pageWidth, pageHeight, marginX, marginY, tableSchema = null) {
    const recordId = fields.ID || rec.id || `Record ${index + 1}`;
    
    // Get field order from table schema
    const fieldOrder = tableSchema && tableSchema.fields 
      ? tableSchema.fields.map(f => f.name)
      : Object.keys(fields);
    
    // Hidden fields that should not be displayed
    const hiddenFields = ['id', 'ID', 'Deliverable date', 'Timestamp'];
    
    // Build ordered field list
    const orderedFields = [];
    fieldOrder.forEach(fieldName => {
      if (!hiddenFields.includes(fieldName) && fields.hasOwnProperty(fieldName)) {
        const value = fields[fieldName];
        const normalizedValue = Utils.normalizeValue(value);
        const attachments = this.extractAttachments(value);
        
        orderedFields.push({
          name: fieldName,
          value: normalizedValue,
          rawValue: value,
          attachments: attachments.length > 0 ? attachments : null
        });
      }
    });

    // Render record content using traditional PDF layout
    this.renderTraditionalRecord(doc, recordId, orderedFields, pageWidth, pageHeight, marginX, marginY);
  }

  /**
   * Render record using traditional PDF layout
   */
  renderTraditionalRecord(doc, recordId, orderedFields, pageWidth, pageHeight, marginX, marginY) {
    const setFillColor = rgb => doc.setFillColor(rgb[0], rgb[1], rgb[2]);
    const setDrawColor = rgb => doc.setDrawColor(rgb[0], rgb[1], rgb[2]);
    const setTextColor = rgb => doc.setTextColor(rgb[0], rgb[1], rgb[2]);

    const contentX = marginX;
    const contentWidth = pageWidth - marginX * 2;
    const lineHeight = 0.19;
    const bottomLimit = pageHeight - marginY;

    const initializeRecordPage = (continued = false, addPage = false) => {
      if (addPage) {
        doc.addPage();
      }
      setFillColor(this.palette.surface);
      doc.rect(0, 0, pageWidth, pageHeight, 'F');

      let nextY = marginY;

      // Header with accent line
      //hiding record number header
      /*doc.setFont('helvetica', 'bold');
      doc.setFontSize(22);
      setTextColor(this.palette.text);
      const heading = continued ? `Record #${recordId} (continued)` : `Record #${recordId}`;
      doc.text(heading, contentX, nextY);
      nextY += 0.15;
      */
      
      // Accent line under header
      // setDrawColor(this.palette.accent);
      // doc.setLineWidth(0.02);
      // doc.line(contentX, nextY, contentX + 2.5, nextY);
      // nextY += 0.45;

      setTextColor(this.palette.text);
      return nextY;
    };

    let y = initializeRecordPage(false, false);

    const ensureSpace = requiredHeight => {
      if (y + requiredHeight > bottomLimit) {
        y = initializeRecordPage(true, true);
      }
    };

    const renderField = (fieldName, fieldValue, attachments = null) => {
      // For attachment fields, only show the label and attachments, not the URL text
      if (attachments && attachments.length > 0) {
        ensureSpace(0.4);
        
        // Field label with subtle background
        setFillColor(this.palette.surfaceAlt);
        doc.roundedRect(contentX, y - 0.12, contentWidth, 0.22, 0.03, 0.03, 'F');
        
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        setTextColor(this.palette.text);
        doc.text(fieldName.toUpperCase(), contentX + 0.12, y);
        y += 0.25;
        
        // Render attachments with bullet points
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        setTextColor([29, 78, 216]);
        attachments.forEach((attachment, idx) => {
          ensureSpace(0.2);
          // Bullet point
          doc.setFont('helvetica', 'normal');
          setTextColor(this.palette.muted);
          doc.text('•', contentX + 0.12, y);
          // Attachment link
          setTextColor([29, 78, 216]);
          doc.textWithLink(attachment.name, contentX + 0.25, y, { url: attachment.url });
          y += 0.18;
        });
        y += 0.2;
        return;
      }
      
      // Skip empty fields or fields with value 0
      if (!fieldValue || fieldValue === '—' || fieldValue.trim() === '' || fieldValue === '0') return;
      
      const lines = doc.splitTextToSize(fieldValue, contentWidth - 0.24);
      const fieldHeight = lines.length * lineHeight + 0.35;
      
      ensureSpace(fieldHeight);
      
      // Field label with subtle background
      setFillColor(this.palette.surfaceAlt);
      doc.roundedRect(contentX, y - 0.12, contentWidth, 0.22, 0.03, 0.03, 'F');
      
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(10);
      setTextColor(this.palette.text);
      doc.text(fieldName.toUpperCase(), contentX + 0.12, y);
      y += 0.28;
      
      // Field value with proper indentation
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(10.5);
      setTextColor(this.palette.text);
      doc.text(lines, contentX + 0.12, y);
      y += lines.length * lineHeight + 0.2;
    };

    const renderMetricsRow = (metrics) => {
      if (metrics.length === 0) return;
      
      ensureSpace(0.9);
      
      const cardGap = 0.2;
      const cardWidth = (contentWidth - cardGap * (metrics.length - 1)) / metrics.length;
      const cardHeight = 0.75;
      
      metrics.forEach((metric, idx) => {
        const cardX = contentX + idx * (cardWidth + cardGap);
        
        // Card background
        setFillColor(this.palette.surfaceAlt);
        doc.roundedRect(cardX, y, cardWidth, cardHeight, 0.08, 0.08, 'F');
        
        // Metric label
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8.5);
        setTextColor(this.palette.muted);
        const labelLines = doc.splitTextToSize(metric.label.toUpperCase(), cardWidth - 0.2);
        doc.text(labelLines, cardX + 0.1, y + 0.2);
        
        // Metric value
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(16);
        setTextColor(this.palette.accent);
        doc.text(String(metric.value), cardX + 0.1, y + 0.55);
      });
      
      y += cardHeight + 0.25;
    };

    // Separate metrics from regular fields
    const metricFieldNames = ['Number Registered', 'Number Attended', 'States Engaged'];
    const metrics = [];
    const regularFields = [];
    
    orderedFields.forEach(field => {
      if (metricFieldNames.includes(field.name)) {
        const numValue = parseFloat(field.value);
        if (!isNaN(numValue) && numValue > 0) {
          metrics.push({
            label: field.name,
            value: numValue
          });
        }
      } else {
        regularFields.push(field);
      }
    });

    // Render metrics row first if there are any
    renderMetricsRow(metrics);

    // Render all regular fields
    regularFields.forEach(field => {
      renderField(field.name, field.value, field.attachments);
    });
  }

  /**
   * Extract attachments from field value
   */
  extractAttachments(value) {
    const items = [];
    const addItem = (entry, fallbackName = 'Attachment') => {
      if (!entry) return;
      if (typeof entry === 'string') {
        const url = entry;
        if (/^https?:\/\//i.test(url)) {
          items.push({ name: fallbackName, url });
        }
        return;
      }
      if (typeof entry === 'object') {
        const url = entry.url || entry.href || entry.link;
        if (!url) return;
        const name = entry.name || entry.filename || entry.title || fallbackName;
        items.push({ name, url });
      }
    };

    if (Array.isArray(value)) {
      value.forEach((entry, idx) => addItem(entry, `Attachment ${idx + 1}`));
    } else if (value) {
      addItem(value);
    }
    return items;
  }
}
