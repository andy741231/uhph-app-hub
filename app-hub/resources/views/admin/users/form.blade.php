@php($editing = isset($managedUser))
<div class="form-grid">
    <div class="field">
        <label class="label" for="name">Full name</label>
        <input class="input" id="name" name="name" value="{{ old('name', $managedUser->name ?? '') }}" required autocomplete="name" @error('name') aria-invalid="true" @enderror>
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label class="label" for="email">Email address</label>
        <input class="input" id="email" name="email" type="email" value="{{ old('email', $managedUser->email ?? '') }}" required autocomplete="email" @error('email') aria-invalid="true" @enderror>
        @error('email')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label class="label" for="password">Password (optional)</label>
        <input class="input" id="password" name="password" type="password" minlength="8" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
        @if ($localEnabled)
            <p class="hint">Leave blank to create the account without a local password. The user can set one later from the Hub login page.</p>
        @else
            <p class="hint">Local sign-in is currently disabled. A stored password becomes usable only after switching to local or hybrid mode.</p>
        @endif
        @error('password')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label class="label" for="password_confirmation">Confirm password</label>
        <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
    </div>
    <div class="field">
        <label class="label" for="status">Account status</label>
        <select class="input" id="status" name="status" required>
            <option value="active" @selected(old('status', $managedUser->status ?? 'active') === 'active')>Active</option>
            <option value="disabled" @selected(old('status', $managedUser->status ?? 'active') === 'disabled')>Disabled</option>
        </select>
        @error('status')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <span class="label">Hub permissions</span>
        <input type="hidden" name="is_admin" value="0">
        <label class="check" for="is_admin">
            <input id="is_admin" name="is_admin" type="checkbox" value="1" @checked(old('is_admin', $managedUser->is_admin ?? false))>
            <span>UHPH App Hub administrator</span>
        </label>
        @error('is_admin')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    @if ($editing && $ssoEnabled)
        <div class="field field-full">
            <label class="label" for="external_subject">SSO subject</label>
            <input class="input" id="external_subject" name="external_subject" value="{{ old('external_subject', $managedUser->external_subject ?? '') }}" autocomplete="off" spellcheck="false">
            <p class="hint">Bound automatically on the first CougarNet sign-in. Set it manually only to fix an account whose sign-in failed with a link error (see the failed sign-in audit entry).</p>
            @error('external_subject')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    @endif
</div>
