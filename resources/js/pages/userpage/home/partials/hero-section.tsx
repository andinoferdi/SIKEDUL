import { login, register } from "@/routes"
import { Link } from "@inertiajs/react"
import { Calendar, CheckCircle2, Zap } from "lucide-react"

export default function HeroSection() {
  return (
    <section className="relative overflow-hidden py-20 lg:py-32">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:items-center">
          <div className="max-w-2xl">
            <div className="mb-6 inline-flex items-center rounded-full bg-secondary px-3 py-1 text-sm font-medium text-secondary-foreground">
              <Zap className="mr-2 h-4 w-4" />
              <span>Kelola waktu lebih cerdas</span>
            </div>
            <h1 className="mb-6 text-4xl font-extrabold tracking-tight text-foreground sm:text-6xl">
              Atur Jadwal Anda dengan <span className="text-primary">SIKEDUL</span>
            </h1>
            <p className="mb-8 text-lg text-muted-foreground sm:text-xl">
              Solusi manajemen waktu terpadu untuk produktivitas maksimal. Sinkronkan kalender, kelola tugas, dan
              dapatkan pengingat cerdas dalam satu platform.
            </p>
            <div className="flex flex-col gap-4 sm:flex-row">
              <Link
                href={register()}
                className="inline-flex h-12 items-center justify-center rounded-full bg-primary px-8 text-base font-semibold text-primary-foreground shadow-lg transition-all hover:scale-105 hover:bg-primary/90"
              >
                Mulai Gratis
              </Link>
              <Link
                href={login()}
                className="inline-flex h-12 items-center justify-center rounded-full border bg-background px-8 text-base font-semibold text-foreground transition-all hover:bg-accent"
              >
                Lihat Demo
              </Link>
            </div>
            <div className="mt-10 flex items-center gap-6 text-sm text-muted-foreground">
              <div className="flex items-center gap-2">
                <CheckCircle2 className="h-5 w-5 text-primary" />
                <span>Mudah digunakan</span>
              </div>
              <div className="flex items-center gap-2">
                <CheckCircle2 className="h-5 w-5 text-primary" />
                <span>Aman & Pribadi</span>
              </div>
            </div>
          </div>
          <div className="relative">
            <div className="relative aspect-square overflow-hidden rounded-3xl bg-gradient-to-br from-primary/20 to-secondary p-8 shadow-2xl">
              <div className="h-full w-full rounded-2xl bg-card p-6 shadow-inner">
                <div className="mb-4 flex items-center justify-between">
                  <div className="h-4 w-24 rounded bg-muted"></div>
                  <div className="h-8 w-8 rounded-full bg-primary/20"></div>
                </div>
                <div className="space-y-3">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="flex items-center gap-3 rounded-lg border p-3">
                      <div className="h-4 w-4 rounded-full border-2 border-primary"></div>
                      <div className="h-3 w-32 rounded bg-muted"></div>
                    </div>
                  ))}
                </div>
                <div className="mt-8">
                  <Calendar className="h-full w-full text-primary/10" strokeWidth={0.5} />
                </div>
              </div>
            </div>
            <div className="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-primary/10 blur-2xl"></div>
            <div className="absolute -bottom-8 -left-8 h-32 w-32 rounded-full bg-secondary/20 blur-3xl"></div>
          </div>
        </div>
      </div>
    </section>
  )
}
