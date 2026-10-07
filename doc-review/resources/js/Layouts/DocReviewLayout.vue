<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const user = computed(() => page.props.auth?.user);
const hub = computed(() => page.props.hub || {});
const flash = computed(() => page.props.flash || {});

// Users assigned to more than one Hub application can hop back to the
// launcher. This is a normal anchor because it crosses origins/apps.
const showAllApplications = computed(() => (hub.value.applicationCount || 0) > 1);

// CSRF token for the browser-native logout form. A full-page POST is used
// (instead of an Inertia visit) so the coordinated Hub logout redirect
// chain works across origins.
const csrfToken = computed(() =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
);

const mobileNavOpen = ref(false);

const focusRing = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-uh-red focus-visible:ring-offset-1';

const navLinkClass = (active) => [
    'inline-flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150',
    focusRing,
    active
        ? 'bg-uh-red/10 text-uh-red'
        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100',
];

// Matches the Flipbook outlined "btn btn-secondary btn-sm" control style.
const outlineButtonClass = [
    'inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white',
    'px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm',
    'hover:bg-gray-50 transition-colors duration-150',
    focusRing,
].join(' ');
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <nav class="sticky top-0 z-50 bg-white border-b border-gray-200 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center gap-2 min-w-0">
                        <Link
                            :href="route('docs.index')"
                            class="flex items-center gap-2 shrink-0 rounded-md"
                            :class="focusRing"
                        >
                            <i class="fa-solid fa-file-lines text-2xl text-uh-red" aria-hidden="true"></i>
                            <span class="font-bold text-xl text-uh-red truncate">
                                Document Reviewer
                            </span>
                        </Link>
                        <div class="hidden md:flex md:items-center md:gap-1 md:ml-6">
                            <Link
                                :href="route('docs.index')"
                                :class="navLinkClass(route().current('docs.index'))"
                                :aria-current="route().current('docs.index') ? 'page' : undefined"
                            >
                                Documents
                            </Link>
                            <Link
                                v-if="user?.canManageFlagWords"
                                :href="route('docs.flag-words.index')"
                                :class="navLinkClass(route().current('docs.flag-words.*'))"
                                :aria-current="route().current('docs.flag-words.*') ? 'page' : undefined"
                            >
                                Flag Words
                            </Link>
                        </div>
                    </div>

                    <div class="hidden md:flex md:items-center md:gap-3">
                        <a
                            v-if="showAllApplications"
                            :href="hub.baseUrl"
                            :class="outlineButtonClass"
                        >
                            <i class="fa-solid fa-grip text-sm" aria-hidden="true"></i>
                            All Applications
                        </a>
                        <form method="POST" :action="route('logout')">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <button
                                type="submit"
                                :class="outlineButtonClass"
                            >
                                <i class="fa-solid fa-right-from-bracket text-sm" aria-hidden="true"></i>
                                Sign out
                            </button>
                        </form>
                    </div>

                    <div class="flex items-center md:hidden">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center h-11 w-11 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors duration-150"
                            :class="focusRing"
                            :aria-expanded="mobileNavOpen"
                            aria-controls="doc-review-mobile-nav"
                            aria-label="Toggle navigation"
                            @click="mobileNavOpen = !mobileNavOpen"
                        >
                            <i v-if="!mobileNavOpen" class="fa-solid fa-bars text-xl" aria-hidden="true"></i>
                            <i v-else class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-show="mobileNavOpen"
                id="doc-review-mobile-nav"
                class="md:hidden border-t border-gray-200 bg-white"
            >
                <div class="px-4 pt-2 pb-3 space-y-1">
                    <Link
                        :href="route('docs.index')"
                        :class="[navLinkClass(route().current('docs.index')), 'w-full min-h-11']"
                        :aria-current="route().current('docs.index') ? 'page' : undefined"
                        @click="mobileNavOpen = false"
                    >
                        Documents
                    </Link>
                    <Link
                        v-if="user?.canManageFlagWords"
                        :href="route('docs.flag-words.index')"
                        :class="[navLinkClass(route().current('docs.flag-words.*')), 'w-full min-h-11']"
                        :aria-current="route().current('docs.flag-words.*') ? 'page' : undefined"
                        @click="mobileNavOpen = false"
                    >
                        Flag Words
                    </Link>
                    <a
                        v-if="showAllApplications"
                        :href="hub.baseUrl"
                        :class="outlineButtonClass + ' w-full min-h-11'"
                    >
                        <i class="fa-solid fa-grip text-sm" aria-hidden="true"></i>
                        All Applications
                    </a>
                    <div class="border-t border-gray-200 pt-3 mt-2">
                        <form method="POST" :action="route('logout')">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <button
                                type="submit"
                                :class="outlineButtonClass + ' w-full min-h-11'"
                            >
                                <i class="fa-solid fa-right-from-bracket text-sm" aria-hidden="true"></i>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <div
            v-if="flash.message || flash.success || flash.status"
            class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4"
        >
            <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 p-3 text-sm text-green-800 dark:text-green-200">
                {{ flash.message || flash.success || flash.status }}
            </div>
        </div>
        <div
            v-if="flash.error || flash.warning"
            class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4"
        >
            <div class="rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 p-3 text-sm text-red-800 dark:text-red-200">
                {{ flash.error || flash.warning }}
            </div>
        </div>

        <header v-if="$slots.header" class="bg-white dark:bg-gray-800 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
