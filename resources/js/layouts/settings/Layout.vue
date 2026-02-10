<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { appearance } from '@/routes';
import { edit as editPassword } from '@/routes/password';
import { edit } from '@/routes/profile';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
    },
    {
        title: 'Password',
        href: editPassword(),
    },
    {
        title: 'Appearance',
        href: appearance(),
    },
];

// ✅ FIX: Proper SSR-safe current path detection
const currentPath = computed(() => {
    if (typeof window === 'undefined') return '';
    return window.location.pathname;
});

// ✅ FIX: Proper active state detection
const isActive = (href: string | { url: string }) => {
    const targetUrl = typeof href === 'string' ? href : href.url;
    return currentPath.value === new URL(targetUrl, window.location.origin).pathname;
};
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="Settings" description="Manage your profile and account settings" />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-1">
                    <!-- ✅ FIX: Remove as-child, make Link the actual button -->
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="typeof item.href === 'string' ? item.href : item.href.url"
                        :href="item.href"
                        :class="cn(
                            'inline-flex items-center justify-start w-full rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            'hover:bg-accent hover:text-accent-foreground',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                            isActive(item.href)
                                ? 'bg-accent text-accent-foreground'
                                : 'text-muted-foreground'
                        )"
                    >
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>