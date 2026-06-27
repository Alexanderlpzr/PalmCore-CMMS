<template>
    <div class="min-h-full bg-gray-50">

        <!-- ── 1. Encabezado ──────────────────────────────────────────────────── -->
        <div class="bg-white border-b border-gray-100 px-5 lg:px-8 py-5">
            <div class="max-w-5xl mx-auto flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">
                        Bienvenido, <span class="text-emerald-600">{{ firstName }}</span>
                    </h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ auth.tenantName }}</p>
                    <p class="text-xs text-gray-400 mt-0.5 capitalize">{{ formattedDate }}</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-2xl font-bold text-gray-800 tabular-nums leading-none">{{ currentTime }}</p>
                    <!-- Clima: espacio reservado para futura integración -->
                    <p class="text-xs text-gray-400 mt-1 flex items-center justify-end gap-1">
                        <svg class="w-3 h-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z"/>
                        </svg>
                        Clima próximamente
                    </p>
                </div>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-5 lg:px-8 py-6 space-y-8">

            <!-- ── 2. Carrusel Institucional ──────────────────────────────────── -->
            <section v-if="slides.length || slidesLoading" aria-label="Carrusel institucional">
                <div v-if="slidesLoading" class="skeleton h-48 lg:h-64 rounded-2xl" />

                <div v-else class="relative overflow-hidden rounded-2xl shadow-sm h-48 lg:h-64 bg-slate-800 select-none">
                    <!-- Slides -->
                    <div class="flex h-full transition-transform duration-500 ease-in-out"
                        :style="{ transform: `translateX(-${carouselIndex * 100}%)` }">
                        <div v-for="(slide, i) in slides" :key="slide.id"
                            class="min-w-full h-full relative bg-cover bg-center"
                            :style="slide.image_url ? { backgroundImage: `url(${slide.image_url})` } : {}">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent" />
                            <div class="absolute bottom-0 left-0 right-0 px-6 pb-5">
                                <p v-if="slide.subtitle" class="text-xs font-semibold text-white/70 uppercase tracking-widest mb-1">{{ slide.subtitle }}</p>
                                <h2 v-if="slide.title" class="text-lg lg:text-2xl font-bold text-white leading-tight">{{ slide.title }}</h2>
                                <p v-if="slide.description" class="text-sm text-white/80 mt-1 line-clamp-2">{{ slide.description }}</p>
                                <a v-if="slide.button_label && slide.button_url"
                                    :href="slide.button_url" target="_blank" rel="noopener"
                                    class="inline-block mt-3 px-4 py-1.5 text-xs font-semibold bg-white text-gray-900 rounded-lg hover:bg-gray-100 transition-colors">
                                    {{ slide.button_label }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Dots -->
                    <div v-if="slides.length > 1" class="absolute bottom-4 right-5 flex gap-1.5">
                        <button v-for="(_, i) in slides" :key="i" @click="carouselIndex = i"
                            class="w-1.5 h-1.5 rounded-full transition-all duration-200"
                            :class="carouselIndex === i ? 'bg-white w-3' : 'bg-white/40'" />
                    </div>

                    <!-- Arrows -->
                    <template v-if="slides.length > 1">
                        <button @click="prevSlide"
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 bg-black/30 hover:bg-black/50 rounded-full flex items-center justify-center text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <button @click="nextSlide"
                            class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 bg-black/30 hover:bg-black/50 rounded-full flex items-center justify-center text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </template>
                </div>
            </section>

            <!-- ── 2b. Contenido CMS Institucional ──────────────────────────────────── -->
            <section v-if="institutionalContent.length || institutionalLoading" aria-label="Contenido institucional">
                <div v-if="institutionalLoading" class="skeleton h-32 rounded-2xl" />
                <div v-else-if="institutionalContent.length" class="space-y-4">
                    <!-- CMS slides as a mini-banner carousel -->
                    <div v-if="cmsSlides.length" class="space-y-3">
                        <div v-for="item in cmsSlides" :key="item.id"
                            class="relative rounded-2xl overflow-hidden shadow-sm h-36 bg-slate-700 bg-cover bg-center"
                            :style="item.image_url ? { backgroundImage: `url(${item.image_url})` } : {}">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"/>
                            <div class="absolute bottom-0 left-0 right-0 px-5 pb-4">
                                <h3 v-if="item.title" class="text-base font-bold text-white leading-tight">{{ item.title }}</h3>
                                <p v-if="item.subtitle" class="text-xs text-white/70 mt-0.5">{{ item.subtitle }}</p>
                                <a v-if="item.button_text && item.button_url"
                                    :href="item.button_url" target="_blank" rel="noopener"
                                    class="inline-block mt-2 px-3 py-1 text-xs font-semibold bg-white text-gray-900 rounded-lg hover:bg-gray-100 transition-colors">
                                    {{ item.button_text }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- CMS news/communication as cards -->
                    <div v-if="cmsNews.length" class="space-y-2">
                        <div v-for="item in cmsNews" :key="item.id"
                            class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                            <div class="flex gap-3">
                                <div v-if="item.image_url" class="w-16 h-16 rounded-xl overflow-hidden shrink-0 bg-gray-100">
                                    <img :src="item.image_url" :alt="item.title" class="w-full h-full object-cover"/>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-bold text-gray-900 leading-tight">{{ item.title }}</h3>
                                    <p v-if="item.subtitle" class="text-xs text-gray-500 mt-0.5">{{ item.subtitle }}</p>
                                    <p v-if="item.description" class="text-xs text-gray-600 mt-1 line-clamp-2 leading-snug">{{ item.description }}</p>
                                    <a v-if="item.button_text && item.button_url"
                                        :href="item.button_url" target="_blank" rel="noopener"
                                        class="inline-block mt-2 text-xs font-semibold text-emerald-600 hover:text-emerald-800 transition-colors">
                                        {{ item.button_text }} →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PDF/Video links -->
                    <div v-if="cmsPdfs.length || cmsVideos.length" class="grid grid-cols-2 gap-3">
                        <a v-for="item in [...cmsPdfs, ...cmsVideos]" :key="item.id"
                            :href="item.button_url || item.image_url || '#'" target="_blank" rel="noopener"
                            class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3 hover:border-emerald-200 hover:shadow-md transition-all">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                :class="item.type === 'pdf' ? 'bg-red-50' : 'bg-blue-50'">
                                <svg class="w-4 h-4" :class="item.type === 'pdf' ? 'text-red-500' : 'text-blue-500'"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path v-if="item.type === 'pdf'" stroke-linecap="round" stroke-linejoin="round"
                                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    <path v-else stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-800 truncate">{{ item.title }}</p>
                                <p v-if="item.subtitle" class="text-xs text-gray-400 truncate">{{ item.subtitle }}</p>
                            </div>
                        </a>
                    </div>
                </div>
            </section>

            <!-- ── 3. Avisos importantes ───────────────────────────────────────── -->
            <section aria-label="Avisos importantes">
                <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Avisos importantes</h2>

                <div v-if="noticesLoading" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div v-for="i in 4" :key="i" class="skeleton h-20 rounded-2xl" />
                </div>

                <div v-else-if="visibleNotices.length" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <RouterLink v-for="n in visibleNotices" :key="n.type"
                        :to="{ name: n.route }"
                        class="bg-white rounded-2xl border shadow-sm p-4 flex flex-col gap-2 hover:shadow-md transition-shadow"
                        :class="noticeBorder[n.color]">
                        <div class="flex items-center justify-between">
                            <span class="text-2xl font-bold tabular-nums" :class="noticeText[n.color]">{{ n.count }}</span>
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center" :class="noticeBg[n.color]">
                                <svg class="w-4 h-4" :class="noticeText[n.color]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs font-semibold text-gray-700 leading-tight">{{ n.label }}</p>
                    </RouterLink>
                </div>

                <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-8 flex flex-col items-center gap-2 text-center">
                    <svg class="w-8 h-8 text-emerald-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-xs font-semibold text-gray-500">Todo al día — sin avisos pendientes</p>
                </div>
            </section>

            <!-- ── 6. Accesos rápidos ──────────────────────────────────────────── -->
            <section aria-label="Accesos rápidos">
                <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Accesos rápidos</h2>
                <div class="grid grid-cols-3 lg:grid-cols-6 gap-3">
                    <RouterLink v-for="qa in quickActions" :key="qa.label"
                        :to="{ name: qa.route }"
                        class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:border-emerald-200 hover:shadow-md transition-all p-4 flex flex-col items-center gap-2 text-center">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="qa.bg">
                            <svg class="w-5 h-5" :class="qa.color" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" v-html="qa.icon" />
                        </div>
                        <p class="text-xs font-semibold text-gray-700 leading-tight">{{ qa.label }}</p>
                    </RouterLink>
                </div>
            </section>

            <!-- ── 4. Noticias y Comunicados ──────────────────────────────────── -->
            <section aria-label="Noticias y comunicados">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500">Noticias y comunicados</h2>
                </div>

                <div v-if="announcementsLoading" class="space-y-3">
                    <div v-for="i in 3" :key="i" class="skeleton h-24 rounded-2xl" />
                </div>

                <div v-else-if="announcements.length" class="space-y-3">
                    <div v-for="a in announcements" :key="a.id"
                        class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:border-gray-200 transition-colors">
                        <div class="flex gap-4 p-4">
                            <!-- Image -->
                            <div v-if="a.image_url" class="w-20 h-20 rounded-xl overflow-hidden shrink-0 bg-gray-100">
                                <img :src="a.image_url" :alt="a.title" class="w-full h-full object-cover" />
                            </div>
                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start gap-2 flex-wrap mb-1">
                                    <span v-if="a.is_pinned" class="text-xs font-semibold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">📌 Fijado</span>
                                    <span class="text-xs font-semibold px-1.5 py-0.5 rounded"
                                        :class="categoryBadge[a.category_color]">
                                        {{ a.category_label }}
                                    </span>
                                </div>
                                <h3 class="text-sm font-bold text-gray-900 leading-tight">{{ a.title }}</h3>
                                <p v-if="a.subtitle" class="text-xs text-gray-500 mt-0.5">{{ a.subtitle }}</p>
                                <p v-if="a.body" class="text-xs text-gray-600 mt-1 line-clamp-2 leading-snug">{{ a.body }}</p>
                                <div class="flex items-center gap-3 mt-2">
                                    <span class="text-xs text-gray-400">{{ relativeTime(a.published_at) }}</span>
                                    <span v-if="a.author_name" class="text-xs text-gray-400">· {{ a.author_name }}</span>
                                    <a v-if="a.button_label && a.button_url"
                                        :href="a.button_url" target="_blank" rel="noopener"
                                        class="text-xs font-semibold text-emerald-600 hover:text-emerald-800 transition-colors ml-auto">
                                        {{ a.button_label }} →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 text-center text-xs text-gray-500">
                    Sin comunicados publicados
                </div>
            </section>

            <!-- ── 5. Feed Empresarial ───────────────────────────────────────────────── -->
            <section aria-label="Feed empresarial">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500">Feed empresarial</h2>
                </div>

                <!-- Filter tabs -->
                <div class="flex gap-1.5 mb-4 overflow-x-auto pb-1 scrollbar-hide">
                    <button
                        v-for="f in feedFilters" :key="f.value"
                        @click="onFeedFilterChange(f.value)"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg whitespace-nowrap transition-colors"
                        :class="feedFilter === f.value
                            ? 'bg-emerald-600 text-white'
                            : 'bg-white border border-gray-200 text-gray-600 hover:border-emerald-300 hover:text-emerald-700'"
                    >
                        {{ f.label }}
                    </button>
                </div>

                <!-- Loading skeleton -->
                <div v-if="feedLoading" class="space-y-3">
                    <div v-for="i in 5" :key="i" class="skeleton h-20 rounded-2xl" />
                </div>

                <!-- Feed items -->
                <div v-else-if="feedItems.length" class="space-y-2">
                    <component
                        :is="item.action_route && item.action_id && ['ops.ordenes.show', 'ops.equipos.show', 'ops.solicitudes.show'].includes(item.action_route) ? RouterLink : 'div'"
                        v-for="item in feedItems"
                        :key="item.id"
                        v-bind="item.action_route && item.action_id && ['ops.ordenes.show', 'ops.equipos.show', 'ops.solicitudes.show'].includes(item.action_route)
                            ? { to: { name: item.action_route, params: { id: item.action_id } } }
                            : {}"
                        class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3 flex items-start gap-3 hover:border-gray-200 hover:shadow-md transition-all"
                    >
                        <!-- Icon -->
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                            :class="(feedIconColor[item.type] ?? 'bg-gray-50 text-gray-400').split(' ').slice(0,1).join(' ')">
                            <svg class="w-4 h-4"
                                :class="(feedIconColor[item.type] ?? 'bg-gray-50 text-gray-400').split(' ').slice(1).join(' ')"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                                v-html="feedIconMap[item.icon_type] ?? feedIconMap.wrench" />
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 leading-tight">{{ item.title }}</p>
                            <p v-if="item.subtitle" class="text-xs text-gray-500 mt-0.5 truncate">{{ item.subtitle }}</p>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="text-xs text-gray-400">{{ item.occurred_at_relative }}</span>
                                <span v-if="!['ops.ordenes.show','ops.equipos.show','ops.solicitudes.show'].includes(item.action_route) && item.action_label && item.action_route"
                                    class="text-xs font-semibold text-emerald-600">
                                    {{ item.action_label }} →
                                </span>
                            </div>
                        </div>

                        <!-- Chevron for linked cards -->
                        <svg v-if="['ops.ordenes.show','ops.equipos.show','ops.solicitudes.show'].includes(item.action_route)"
                            class="w-4 h-4 text-gray-300 shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                        </svg>
                    </component>

                    <!-- Infinite scroll sentinel -->
                    <div ref="feedSentinel" class="h-4 w-full" />

                    <!-- Load more indicator -->
                    <div v-if="feedLoadingMore" class="flex justify-center py-4">
                        <div class="w-5 h-5 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin" />
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 text-center text-xs text-gray-500">
                    Sin actividad reciente
                </div>
            </section>

            <!-- ── 7. Espacio reservado — futuras integraciones ───────────────── -->
            <section aria-label="Futuras integraciones">
                <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Próximamente</h2>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div v-for="future in futureWidgets" :key="future.label"
                        class="bg-white rounded-2xl border border-dashed border-gray-200 p-5 flex flex-col items-center gap-2 text-center opacity-60">
                        <div class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" v-html="future.icon" />
                        </div>
                        <p class="text-xs font-semibold text-gray-400">{{ future.label }}</p>
                    </div>
                </div>
            </section>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useAuthStore } from '../stores/auth.js'

const api  = useApi()
const auth = useAuthStore()

// ── Clock ─────────────────────────────────────────────────────────────────────

const now = ref(new Date())
let clockTimer

const currentTime = computed(() => now.value.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }))
const formattedDate = computed(() => now.value.toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))
const firstName = computed(() => auth.userName?.split(' ')[0] ?? 'Usuario')

// ── Carousel ──────────────────────────────────────────────────────────────────

const slides         = ref([])
const slidesLoading  = ref(true)
const carouselIndex  = ref(0)
let carouselTimer

function nextSlide() { carouselIndex.value = (carouselIndex.value + 1) % slides.value.length }
function prevSlide() { carouselIndex.value = (carouselIndex.value - 1 + slides.value.length) % slides.value.length }

// ── Notices ───────────────────────────────────────────────────────────────────

const notices        = ref([])
const noticesLoading = ref(true)
const visibleNotices = computed(() => notices.value.filter(n => n.visible))

const noticeBorder = { red: 'border-red-100', amber: 'border-amber-100', blue: 'border-blue-100', orange: 'border-orange-100' }
const noticeBg     = { red: 'bg-red-50', amber: 'bg-amber-50', blue: 'bg-blue-50', orange: 'bg-orange-50' }
const noticeText   = { red: 'text-red-600', amber: 'text-amber-600', blue: 'text-blue-600', orange: 'text-orange-600' }

// ── Quick actions ─────────────────────────────────────────────────────────────

const quickActions = [
    { label: 'Crear OT',        route: 'ops.ordenes',    bg: 'bg-indigo-50', color: 'text-indigo-500', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>' },
    { label: 'Solicitud',       route: 'ops.solicitudes', bg: 'bg-emerald-50', color: 'text-emerald-500', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>' },
    { label: 'Equipos',         route: 'ops.equipos',    bg: 'bg-slate-50',   color: 'text-slate-500',   icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/>' },
    { label: 'Preventivos',     route: 'ops.preventivos', bg: 'bg-amber-50', color: 'text-amber-500',   icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>' },
    { label: 'Alertas',         route: 'ops.alertas',    bg: 'bg-red-50',    color: 'text-red-500',     icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>' },
    { label: 'Dashboard',       route: 'ops.dashboard',  bg: 'bg-blue-50',   color: 'text-blue-500',    icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>' },
]

// ── Category badges ───────────────────────────────────────────────────────────

const categoryBadge = {
    blue:    'bg-blue-100 text-blue-700',
    emerald: 'bg-emerald-100 text-emerald-700',
    violet:  'bg-violet-100 text-violet-700',
    amber:   'bg-amber-100 text-amber-700',
}

// ── Future widgets ────────────────────────────────────────────────────────────

const futureWidgets = [
    { label: 'Clima en tiempo real', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z"/>' },
    { label: 'Producción',           icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5"/>' },
    { label: 'IoT / Sensores',       icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/>' },
    { label: 'Consumo energético',   icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>' },
]

// ── Institutional CMS content ─────────────────────────────────────────────────

const institutionalContent  = ref([])
const institutionalLoading  = ref(true)

const cmsSlides  = computed(() => institutionalContent.value.filter(i => i.type === 'image'))
const cmsNews    = computed(() => institutionalContent.value.filter(i => ['news', 'communication'].includes(i.type)))
const cmsVideos  = computed(() => institutionalContent.value.filter(i => i.type === 'video'))
const cmsPdfs    = computed(() => institutionalContent.value.filter(i => i.type === 'pdf'))

// ── Data ──────────────────────────────────────────────────────────────────────

const announcements        = ref([])
const announcementsLoading = ref(true)

// ── Feed Empresarial ──────────────────────────────────────────────────────────
const feedItems          = ref([])
const feedLoading        = ref(true)
const feedLoadingMore    = ref(false)
const feedPage           = ref(1)
const feedNextPage       = ref(null)
const feedFilter         = ref('all')
const feedSentinel       = ref(null)

const feedFilters = [
    { value: 'all',         label: 'Todo' },
    { value: 'work_order',  label: 'OT' },
    { value: 'equipment',   label: 'Equipos' },
    { value: 'request',     label: 'Solicitudes' },
    { value: 'maintenance', label: 'Mantenimiento' },
]

const feedIconMap = {
    wrench:    '<path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/>',
    check:     '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    x:         '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    comment:   '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>',
    camera:    '<path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>',
    equipment: '<path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/>',
    clipboard: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>',
    tools:     '<path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/>',
}

const feedIconColor = {
    work_order_created:    'bg-indigo-50 text-indigo-500',
    work_order_completed:  'bg-emerald-50 text-emerald-500',
    work_order_cancelled:  'bg-gray-100 text-gray-400',
    comment_added:         'bg-blue-50 text-blue-500',
    evidence_added:        'bg-purple-50 text-purple-500',
    equipment_created:     'bg-slate-50 text-slate-500',
    request_created:       'bg-amber-50 text-amber-500',
    maintenance_completed: 'bg-teal-50 text-teal-500',
}

function relativeTime(iso) {
    if (!iso) { return '' }
    const diff = Date.now() - new Date(iso).getTime()
    const h = Math.floor(diff / 36e5)
    if (h < 1) { return 'hace menos de 1h' }
    if (h < 24) { return `hace ${h}h` }
    return `hace ${Math.floor(h / 24)}d`
}

async function loadAll() {
    const [slidesRes, noticesRes, announcementsRes, contentRes] = await Promise.allSettled([
        api.get('home/carousel'),
        api.get('home/notices'),
        api.get('home/announcements'),
        api.get('home/content'),
    ])

    slides.value               = slidesRes.status === 'fulfilled' ? (slidesRes.value?.data ?? []) : []
    notices.value              = noticesRes.status === 'fulfilled' ? (noticesRes.value?.data ?? []) : []
    announcements.value        = announcementsRes.status === 'fulfilled' ? (announcementsRes.value?.data ?? []) : []
    institutionalContent.value = contentRes.status === 'fulfilled' ? (contentRes.value?.data ?? []) : []

    slidesLoading.value        = false
    noticesLoading.value       = false
    announcementsLoading.value = false
    institutionalLoading.value = false

    if (slides.value.length > 1) {
        carouselTimer = setInterval(nextSlide, 5000)
    }
}

async function loadFeed(reset = false) {
    if (reset) {
        feedItems.value = []
        feedPage.value = 1
        feedNextPage.value = null
        feedLoading.value = true
    }
    try {
        const res = await api.get(`home/feed?filter=${feedFilter.value}&page=${feedPage.value}`)
        const d = res?.data ?? {}
        if (reset) {
            feedItems.value = d.items ?? []
        } else {
            feedItems.value = [...feedItems.value, ...(d.items ?? [])]
        }
        feedNextPage.value = d.next_page ?? null
    } catch (_) {
        // silently fail — feed is non-critical
    } finally {
        feedLoading.value = false
        feedLoadingMore.value = false
    }
}

async function loadMoreFeed() {
    if (feedLoadingMore.value || !feedNextPage.value) { return }
    feedLoadingMore.value = true
    feedPage.value = feedNextPage.value
    await loadFeed(false)
}

function onFeedFilterChange(filter) {
    feedFilter.value = filter
    loadFeed(true)
}

let feedObserver = null

onMounted(() => {
    clockTimer = setInterval(() => { now.value = new Date() }, 1000)
    loadAll()
    loadFeed(true)

    feedObserver = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && feedNextPage.value) {
            loadMoreFeed()
        }
    }, { threshold: 0.1 })

    watch(feedSentinel, (el) => {
        if (el) { feedObserver.observe(el) }
    })
})

onUnmounted(() => {
    clearInterval(clockTimer)
    clearInterval(carouselTimer)
    feedObserver?.disconnect()
})
</script>
