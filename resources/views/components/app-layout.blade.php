<!-- Minimal Blade layout for app-layout component -->
<div class="min-h-screen bg-gray-100">
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $header ?? 'App' }}
            </h2>
        </div>
    </header>
    <main>
        {{ $slot }}
    </main>
</div>
