import { Head } from "@inertiajs/react"
import NavigationHeader from "@/pages/userpage/home/partials/navigation-header"
import HeroSection from "@/pages/userpage/home/partials/hero-section"
import FeatureSection from "@/pages/userpage/home/partials/feature-section"

export default function Welcome({ canRegister = true }: { canRegister?: boolean }) {
  return (
    <div className="min-h-screen bg-background font-sans text-foreground">
      <Head title="SIKEDUL - Manajemen Jadwal Cerdas">

      </Head>

      <NavigationHeader canRegister={canRegister} />

      <main>
        <HeroSection />
        <FeatureSection />

        {/* Footer simple */}
        <footer className="border-t py-12">
          <div className="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
            <p className="text-sm text-muted-foreground">
              &copy; {new Date().getFullYear()} SIKEDUL. Hak Cipta Dilindungi.
            </p>
          </div>
        </footer>
      </main>
    </div>
  )
}
