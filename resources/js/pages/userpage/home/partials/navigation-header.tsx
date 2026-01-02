import { dashboard, login, register } from "@/routes"
import type { SharedData } from "@/types"
import { Link, usePage } from "@inertiajs/react"
import { CalendarDays } from "lucide-react"

export default function NavigationHeader({ canRegister }: { canRegister: boolean }) {
  const { auth } = usePage<SharedData>().props

  return (
    <header className="sticky top-0 z-50 w-full border-b bg-background/80 backdrop-blur-md">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div className="flex items-center gap-2">
          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
            <CalendarDays className="h-6 w-6" />
          </div>
          <span className="text-xl font-bold tracking-tight text-foreground">SIKEDUL</span>
        </div>
        <nav className="flex items-center gap-4">
          {auth.user ? (
            <Link
              href={dashboard()}
              className="inline-flex h-10 items-center justify-center rounded-full bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
            >
              Dashboard
            </Link>
          ) : (
            <>
              <Link
                href={login()}
                className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
              >
                Masuk
              </Link>
              {canRegister && (
                <Link
                  href={register()}
                  className="inline-flex h-10 items-center justify-center rounded-full bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                >
                  Daftar Sekarang
                </Link>
              )}
            </>
          )}
        </nav>
      </div>
    </header>
  )
}
