@props(['user' => null, 'required' => false])

@php
    $reqMark = $required ? ' <span class="req">*</span>' : '';
    $reqAttr = $required ? 'required' : '';
@endphp

{{-- Phone Number --}}
<div>
    <label for="phone" class="label">Phone Number{!! $reqMark !!}</label>
    <input type="tel" id="phone" name="phone" value="{{ old('phone', $user?->phone) }}"
        class="input" autocomplete="tel" placeholder="e.g. 713-743-0000" {{ $reqAttr }}>
    @error('phone')
        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- Department --}}
<div>
    <label for="department" class="label">Department{!! $reqMark !!}</label>
    <input type="text" id="department" name="department" value="{{ old('department', $user?->department) }}"
        class="input" autocomplete="organization" placeholder="e.g. Health Informatics" {{ $reqAttr }}>
    @error('department')
        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- Title --}}
<div>
    <label for="title" class="label">Title{!! $reqMark !!}</label>
    <input type="text" id="title" name="title" value="{{ old('title', $user?->title) }}"
        class="input" autocomplete="organization-title" placeholder="e.g. Assistant Professor" {{ $reqAttr }}>
    @error('title')
        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- PeopleSoft ID --}}
<div>
    <label for="peoplesoft_id" class="label">PeopleSoft ID{!! $reqMark !!}</label>
    <input type="text" id="peoplesoft_id" name="peoplesoft_id" value="{{ old('peoplesoft_id', $user?->peoplesoft_id) }}"
        class="input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit number" {{ $reqAttr }}>
    <p class="text-xs text-gray-500 mt-1">Enter your 6-digit PeopleSoft ID.</p>
    @error('peoplesoft_id')
        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- Investigator Type --}}
<div>
    <label for="investigator_type" class="label">Investigator Type{!! $reqMark !!}</label>
    <select id="investigator_type" name="investigator_type" class="input" {{ $reqAttr }}>
        <option value="">Select...</option>
        <option value="early_stage" {{ old('investigator_type', $user?->investigator_type) === 'early_stage' ? 'selected' : '' }}>Early-Stage Investigator</option>
        <option value="new" {{ old('investigator_type', $user?->investigator_type) === 'new' ? 'selected' : '' }}>New Investigator</option>
    </select>
    @error('investigator_type')
        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
    @enderror
</div>
