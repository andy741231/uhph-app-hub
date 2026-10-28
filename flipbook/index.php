<?php
/**
 * Dashboard - List all flipbooks
 */
require_once __DIR__ . '/includes/auth.php';

$navIndexUser = flipbook_current_user();
$indexIsAdmin = flipbook_is_admin();
$indexSignedIn = $navIndexUser !== null || !FLIPBOOK_HUB_SSO_ENABLED;
// Anonymous visitors get the public gallery; signed-in users get their
// dashboard (admin sees everything, user sees only their own uploads) and
// can switch back to the public gallery via ?view=public.
$indexPublicView = $indexSignedIn && ($_GET['view'] ?? '') === 'public';
$dashboardHeading = $indexIsAdmin ? 'All Flipbooks' : 'My Flipbooks';
$heading = $indexPublicView || !$indexSignedIn ? 'Flipbooks' : $dashboardHeading;

$pageTitle = 'Dashboard';
require_once 'includes/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
</div>

<?php if ($indexSignedIn): ?>
<div class="scope-tabs" id="scopeTabs" aria-label="Flipbook list view">
    <button type="button" class="scope-tab<?= $indexPublicView ? '' : ' active' ?>" data-scope="dashboard" aria-pressed="<?= $indexPublicView ? 'false' : 'true' ?>"><?= $dashboardHeading ?></button>
    <button type="button" class="scope-tab<?= $indexPublicView ? ' active' : '' ?>" data-scope="public" aria-pressed="<?= $indexPublicView ? 'true' : 'false' ?>">Public Gallery</button>
</div>
<?php endif; ?>

<div id="flipbook-list">
    <div class="text-center mt-3">
        <div class="spinner" style="margin: 0 auto;"></div>
        <p class="mt-1" style="color:var(--gray-500);">Loading flipbooks...</p>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal">
        <div class="modal-header">
            <span>Delete Flipbook</span>
            <button class="btn btn-icon btn-ghost" onclick="closeModal('deleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete <strong id="deleteTitle"></strong>? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
            <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
        </div>
    </div>
</div>

<!-- Embed Code Modal -->
<div class="modal-backdrop" id="embedModal">
    <div class="modal">
        <div class="modal-header">
            <span>Embed Flipbook</span>
            <button class="btn btn-icon btn-ghost" onclick="closeModal('embedModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="mb-2">Copy and paste this code to embed the flipbook on your website:</p>
            <div class="embed-code" id="embedCode"></div>
            <div class="mt-2">
                <button class="btn btn-primary btn-sm" onclick="copyEmbedCode()">
                    <i class="fas fa-copy"></i> Copy Code
                </button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const BASE_PATH = '<?= BASE_PATH ?>';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const IS_SIGNED_IN = <?= $indexSignedIn ? 'true' : 'false' ?>;
const IS_ADMIN = <?= $indexIsAdmin ? 'true' : 'false' ?>;
const DASHBOARD_HEADING = <?= json_encode($dashboardHeading) ?>;
let currentScope = <?= $indexPublicView ? "'public'" : "'dashboard'" ?>;

const scopeTabs = document.getElementById('scopeTabs');
if (scopeTabs) {
    scopeTabs.addEventListener('click', (e) => {
        const btn = e.target.closest('.scope-tab');
        if (!btn || btn.dataset.scope === currentScope) return;
        currentScope = btn.dataset.scope;
        scopeTabs.querySelectorAll('.scope-tab').forEach(b => {
            const active = b === btn;
            b.classList.toggle('active', active);
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        document.querySelector('.page-header h1').textContent =
            currentScope === 'public' ? 'Flipbooks' : DASHBOARD_HEADING;
        const url = new URL(window.location.href);
        if (currentScope === 'public') {
            url.searchParams.set('view', 'public');
        } else {
            url.searchParams.delete('view');
        }
        window.history.replaceState(null, '', url);
        loadFlipbooks();
    });
}

const VISIBILITY_BADGES = {
    public:   { icon: 'fa-globe', label: 'Public' },
    unlisted: { icon: 'fa-link',  label: 'Unlisted' },
    private:  { icon: 'fa-lock',  label: 'Private' },
};

function visibilityBadge(v) {
    // Class and content are taken only from the whitelist above — never
    // from the raw server value.
    const b = VISIBILITY_BADGES[v];
    if (!b) return '';
    return `<span class="visibility-badge visibility-${v}"><i class="fas ${b.icon}"></i> ${b.label}</span>`;
}

async function loadFlipbooks() {
    try {
        const resp = await fetch(currentScope === 'public' ? 'api/flipbooks.php?scope=public' : 'api/flipbooks.php');
        const data = await resp.json();

        const container = document.getElementById('flipbook-list');
        // Manage controls (edit/delete, visibility badges, owner) only make
        // sense on the dashboard scope — the public scope strips owner
        // identity server-side, so cards there are view + embed only.
        const canManage = IS_SIGNED_IN && currentScope === 'dashboard';

        if (!data.flipbooks || data.flipbooks.length === 0) {
            container.innerHTML = canManage ? `
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No flipbooks yet</h3>
                    <p>Upload a PDF to create your first flipbook.</p>
                    <a href="upload.php" class="btn btn-primary mt-2">
                        <i class="fas fa-plus"></i> Upload PDF
                    </a>
                </div>` : `
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No public flipbooks yet</h3>
                    ${IS_SIGNED_IN ? '' : '<p>Sign in to upload and manage flipbooks.</p>'}
                </div>`;
            return;
        }

        container.innerHTML = '<div class="flipbook-grid">' +
            data.flipbooks.map(fb => {
                const fbId = parseInt(fb.id, 10) || 0;
                const fbSlug = encodeURIComponent(String(fb.slug || ''));
                const thumbHtml = fb.thumbnail
                    ? `<img src="api/cover.php?id=${fbId}" alt="Cover" onerror="this.style.display='none';this.insertAdjacentHTML('afterend','<i class=\\'fas fa-file-pdf\\' style=\\'font-size:4rem;color:var(--primary)\\'></i>')">`
                    : `<i class="fas fa-file-pdf" style="font-size: 4rem; color: var(--primary);"></i>`;
                const embedBtn = fb.visibility !== 'private' ? `
                        <button class="btn btn-icon" data-action="embed" title="Embed">
                            <i class="fas fa-code"></i>
                        </button>` : '';
                const actions = IS_SIGNED_IN ? `
                    <div class="card-actions">
                        ${embedBtn}
                        ${canManage ? `
                        <button class="btn btn-icon" data-action="edit" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-icon" data-action="delete" title="Delete" style="color:var(--danger);">
                            <i class="fas fa-trash"></i>
                        </button>` : ''}
                    </div>` : '';
                const badge = canManage ? ' ' + visibilityBadge(fb.visibility) : '';
                const owner = IS_ADMIN && fb.owner_name
                    ? `<p class="card-owner"><i class="fas fa-user"></i> ${escapeHtml(String(fb.owner_name))}</p>` : '';
                return `
                <div class="flipbook-card card" data-slug="${fbSlug}" data-id="${fbId}" data-title="${escapeAttr(String(fb.title || ''))}" tabindex="0" role="link" aria-label="Open ${escapeAttr(String(fb.title || 'flipbook'))}">
                    <div class="card-thumbnail">
                        ${thumbHtml}
                    </div>
                    ${actions}
                    <div class="card-info">
                        <h3>${escapeHtml(fb.title)}${badge}</h3>
                        ${owner}
                        <p>${fb.page_count || 0} pages &middot; ${formatDate(fb.created_at)}</p>
                    </div>
                </div>
            `}).join('') + '</div>';
    } catch (err) {
        document.getElementById('flipbook-list').innerHTML =
            '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error loading flipbooks</h3><p>' + err.message + '</p></div>';
    }
}

// Delegated card interactions — no user data is ever interpolated into
// inline handlers, so titles/slugs can't break out of attributes or JS.
document.getElementById('flipbook-list').addEventListener('click', (e) => {
    const card = e.target.closest('.flipbook-card');
    if (!card) return;
    const { slug, id, title } = card.dataset;
    const actionBtn = e.target.closest('[data-action]');

    if (actionBtn) {
        e.stopPropagation();
        if (actionBtn.dataset.action === 'embed') showEmbed(slug, title);
        if (actionBtn.dataset.action === 'edit') window.location.href = 'editor.php?id=' + encodeURIComponent(id);
        if (actionBtn.dataset.action === 'delete') confirmDelete(id, title);
        return;
    }
    window.location.href = 'viewer.php?slug=' + slug;
});

// Keyboard activation — Enter/Space opens the viewer. Only fires when the
// card itself is the keydown target, so nested action buttons keep their own
// activation (Space scroll/click prevention included).
document.getElementById('flipbook-list').addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const card = e.target.closest('.flipbook-card');
    if (!card || e.target !== card) return;
    e.preventDefault();
    window.location.href = 'viewer.php?slug=' + card.dataset.slug;
});

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function escapeAttr(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Delete
let deleteId = null;
function confirmDelete(id, title) {
    deleteId = id;
    document.getElementById('deleteTitle').textContent = title;
    document.getElementById('deleteModal').classList.add('open');
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
    if (!deleteId) return;
    try {
        const resp = await fetch('api/flipbooks.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ id: deleteId })
        });
        if (!resp.ok) {
            const data = await resp.json().catch(() => ({}));
            throw new Error(data.error || 'Delete failed (' + resp.status + ')');
        }
        closeModal('deleteModal');
        showToast('Flipbook deleted', 'success');
        loadFlipbooks();
    } catch (err) {
        showToast('Failed to delete: ' + err.message, 'error');
    }
});

// Embed
function showEmbed(slug, title) {
    const url = window.location.origin + BASE_PATH + '/viewer.php?slug=' + slug + '&embed=1';
    const code = `<iframe src="${url}" width="100%" height="600" frameborder="0" allowfullscreen title="${title}"></iframe>`;
    document.getElementById('embedCode').textContent = code;
    document.getElementById('embedModal').classList.add('open');
}

function copyEmbedCode() {
    const text = document.getElementById('embedCode').textContent;
    navigator.clipboard.writeText(text).then(() => {
        showToast('Embed code copied!', 'success');
    });
}

// Modal
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

// Toast
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
    container.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 3000);
}

// Click outside modal to close
document.querySelectorAll('.modal-backdrop').forEach(el => {
    el.addEventListener('click', (e) => {
        if (e.target === el) el.classList.remove('open');
    });
});

loadFlipbooks();
</script>

<?php require_once 'includes/footer.php'; ?>
