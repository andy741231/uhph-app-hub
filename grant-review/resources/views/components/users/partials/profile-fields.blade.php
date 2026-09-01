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
        class="input" inputmode="numeric" pattern="[0-9]{7,20}" minlength="7" maxlength="20" placeholder="7+ digit number" {{ $reqAttr }}>
    <p class="text-xs text-gray-500 mt-1">Enter your 7-digit (or longer) PeopleSoft ID.</p>
    @error('peoplesoft_id')
        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- Type of Investigator --}}
<div>
    <label for="investigator_type" class="label">Type of Investigator{!! $reqMark !!}</label>
    <select id="investigator_type" name="investigator_type" class="input" {{ $reqAttr }}>
        <option value="">Select...</option>
        <option value="pi" {{ old('investigator_type', $user?->investigator_type) === 'pi' ? 'selected' : '' }}>Principal Investigator</option>
        <option value="other" {{ old('investigator_type', $user?->investigator_type) === 'other' ? 'selected' : '' }}>Other</option>
    </select>
    @error('investigator_type')
        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- Early-Stage Investigator checkbox --}}
<div class="flex items-start gap-3 pt-1">
    <input type="hidden" name="early_stage_investigator" value="0">
    <input type="checkbox" id="early_stage_investigator" name="early_stage_investigator" value="1"
        class="mt-1 w-4 h-4 rounded border-gray-300 text-uh-red focus:ring-uh-red"
        @checked(old('early_stage_investigator', $user?->early_stage_investigator ?? false))>
    <div>
        <label for="early_stage_investigator" class="text-sm font-medium text-uh-fg cursor-pointer">Early-Stage Investigator</label>
        <p class="text-xs text-gray-500">Check if you are an early-stage investigator.</p>
    </div>
</div>

{{-- New Investigator checkbox --}}
<div class="flex items-start gap-3">
    <input type="hidden" name="new_investigator" value="0">
    <input type="checkbox" id="new_investigator" name="new_investigator" value="1"
        class="mt-1 w-4 h-4 rounded border-gray-300 text-uh-red focus:ring-uh-red"
        @checked(old('new_investigator', $user?->new_investigator ?? false))>
    <div>
        <label for="new_investigator" class="text-sm font-medium text-uh-fg cursor-pointer">New Investigator</label>
        <p class="text-xs text-gray-500">Check if you are a new investigator.</p>
    </div>
</div>

{{-- Key Personnel (dynamic repeater) --}}
<div x-data="{
    personnel: {{ json_encode(old('key_personnel', $user?->key_personnel ?? [])) }},
    add() { this.personnel.push({ title: '', name: '' }) },
    remove(index) { this.personnel.splice(index, 1) },
}">
    <label class="label">Key Personnel</label>
    <p class="text-xs text-gray-500 mb-3">Add additional team members (optional).</p>

    <template x-for="(person, index) in personnel" :key="index">
        <div class="flex items-start gap-2 mb-2">
            <input type="text"
                :name="'key_personnel[' + index + '][title]'"
                x-model="person.title"
                class="input flex-1"
                placeholder="Title (e.g. Co-PI)">
            <input type="text"
                :name="'key_personnel[' + index + '][name]'"
                x-model="person.name"
                class="input flex-1"
                placeholder="Name">
            <button type="button"
                @click="remove(index)"
                class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg border border-uh-border bg-white text-gray-400 hover:text-red-600 hover:border-red-300 transition-colors"
                aria-label="Remove"
                title="Remove">
                <x-heroicon-o-x-mark class="w-4 h-4" />
            </button>
        </div>
    </template>

    <button type="button"
        @click="add()"
        class="inline-flex items-center gap-1.5 text-sm font-semibold text-uh-red hover:text-uh-red/80 transition-colors">
        <x-heroicon-o-plus class="w-4 h-4" />
        Add Key Personnel
    </button>

    @error('key_personnel')
        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
    @enderror
</div>
