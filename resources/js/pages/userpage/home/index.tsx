import { Head } from '@inertiajs/react';
import HeroSection from '@/pages/userpage/home/partials/hero-section';
import LaravelBrandSection from '@/pages/userpage/home/partials/laravel-brand-section';
import NavigationHeader from '@/pages/userpage/home/partials/navigation-header';

export default function Welcome({ canRegister = true }: { canRegister?: boolean }) {
    return (
        <>
            <Head title="Welcome">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=poppins:300,400,500,600,700" rel="stylesheet" />
            </Head>
            <div className="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]">
                <NavigationHeader canRegister={canRegister} />

                <div className="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
                    <main className="flex w-full max-w-[335px] flex-col-reverse lg:max-w-4xl lg:flex-row">
                        <HeroSection />
                        <LaravelBrandSection />
                    </main>
                </div>
                <div className="hidden h-14.5 lg:block"></div>
            </div>
        </>
    );
}
