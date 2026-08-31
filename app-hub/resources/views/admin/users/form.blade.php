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
        <label class="label" for="password">Password{{ $editing ? ' (optional)' : '' }}</label>
        <input class="input" id="password" name="password" type="password" minlength="8" {{ $editing ? '' : 'required' }} autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
        <p class="hint">At least 8 characters containing letters and numbers.</p>
        @error('password')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label class="label" for="password_confirmation">Confirm password</label>
        <input class="input" id="password_confirmation" name="password_confirmation" type="password" {{ $editing ? '' : 'required' }} autocomplete="new-password">
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
</div>
