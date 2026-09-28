@php($editing = isset($application))
<div class="form-grid">
    <div class="field">
        <label class="label" for="name">Application name</label>
        <input class="input" id="name" name="name" value="{{ old('name', $application->name ?? '') }}" required @error('name') aria-invalid="true" @enderror>
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label class="label" for="key">Application key</label>
        <input class="input" id="key" name="key" value="{{ old('key', $application->key ?? '') }}" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" @error('key') aria-invalid="true" @enderror>
        <p class="hint">Lowercase identifier such as grant-review.</p>
        @error('key')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field field-full">
        <label class="label" for="invitation_message">Invitation message</label>
        <textarea class="input" id="invitation_message" name="invitation_message" rows="3" maxlength="1000" @error('invitation_message') aria-invalid="true" @enderror>{{ old('invitation_message', $application->invitation_message ?? '') }}</textarea>
        <div id="invitation-editor" data-invitation-editor></div>
        <p class="hint">Optional app-specific text included in new-user invitation emails. Rich formatting (bold, italic, links, lists) is supported; up to 1,000 characters including markup.</p>
        @error('invitation_message')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field field-full">
        <label class="label" for="path">Application path</label>
        <input class="input" id="path" name="path" value="{{ old('path', $application->path ?? '') }}" required placeholder="/apps/grant-review" @error('path') aria-invalid="true" @enderror>
        <p class="hint">Must be an internal path beginning with /apps/.</p>
        @error('path')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field field-full">
        <label class="label" for="callback_url">SSO callback path</label>
        <input class="input" id="callback_url" name="callback_url" value="{{ old('callback_url', $application->callback_url ?? '') }}" placeholder="/apps/grant-review/auth/hub/callback" @error('callback_url') aria-invalid="true" @enderror>
        <p class="hint">Exact internal callback path. Required before generating client credentials.</p>
        @error('callback_url')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field field-full">
        <label class="label" for="frontchannel_logout_path">Single logout path</label>
        <input class="input" id="frontchannel_logout_path" name="frontchannel_logout_path" value="{{ old('frontchannel_logout_path', $application->frontchannel_logout_path ?? '') }}" placeholder="/apps/grant-review/auth/hub/logout" @error('frontchannel_logout_path') aria-invalid="true" @enderror>
        <p class="hint">Internal endpoint used to clear this application's browser session during global sign out.</p>
        @error('frontchannel_logout_path')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field field-full">
        <label class="label" for="roles">Supported roles</label>
        <input class="input" id="roles" name="roles" value="{{ old('roles', isset($application) ? implode(', ', $application->roles ?? []) : '') }}" placeholder="admin, submitter, reviewer" @error('roles') aria-invalid="true" @enderror>
        <p class="hint">Comma-separated. Leave empty when the application does not use roles.</p>
        @error('roles')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label class="label" for="sort_order">Display order</label>
        <input class="input" id="sort_order" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $application->sort_order ?? 0) }}" required>
        @error('sort_order')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <span class="label">Availability</span>
        <input type="hidden" name="enabled" value="0">
        <label class="check" for="enabled"><input id="enabled" name="enabled" type="checkbox" value="1" @checked(old('enabled', $application->enabled ?? true))><span>Enabled</span></label>
        @error('enabled')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@toast-ui/editor@3.2.2/dist/toastui-editor.css" integrity="sha384-iONCORmrrRFYjYipi1NS4bgFEpQ8vCnQSTma1tan96M0nM1EZOWsRoW5sy3Q/hEl" crossorigin="anonymous">
<style>
    .toastui-editor-defaultUI { border-color: #aeb2b4; border-radius: 8px; font-family: inherit; }
    .toastui-editor-defaultUI-toolbar { border-radius: 8px 8px 0 0; }
    .toastui-editor-contents { font-family: inherit; font-size: 15px; }
    @error('invitation_message')
    .toastui-editor-defaultUI { border-color: var(--red); }
    @enderror
</style>
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@toast-ui/editor@3.2.2/dist/toastui-editor.js" integrity="sha384-eZofczSR5bdafMBF3vhYYKhz1AToDffTgjDAYYHOf49gKXTkavAtTWBhKAILUIiR" crossorigin="anonymous"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var field = document.getElementById('invitation_message');
        var host = document.querySelector('[data-invitation-editor]');
        if (!field || !host || typeof toastui === 'undefined' || !toastui.Editor) {
            return;
        }
        var editor = new toastui.Editor({
            el: host,
            height: '220px',
            initialEditType: 'wysiwyg',
            hideModeSwitch: true,
            usageStatistics: false,
            toolbarItems: [['bold', 'italic'], ['ul', 'ol'], ['link'], ['quote']],
            initialValue: field.value,
            events: {
                change: function () {
                    field.value = editor.getMarkdown();
                },
            },
        });
        field.value = editor.getMarkdown();
        field.style.display = 'none';
        field.closest('form').addEventListener('submit', function () {
            field.value = editor.getMarkdown();
        });
    });
</script>
@endpush
