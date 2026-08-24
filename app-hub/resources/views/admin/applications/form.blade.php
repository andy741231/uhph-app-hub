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
