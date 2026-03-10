import { dashboard, login, register } from "@/routes"
import type { SharedData } from "@/types"
import { Link, usePage } from "@inertiajs/react"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useAppearance } from "@/hooks/use-appearance"
import { Sun } from "lucide-react"

export default function NavigationHeader({ canRegister }: { canRegister: boolean }) {
  const { auth } = usePage<SharedData>().props
  const { appearance, updateAppearance } = useAppearance()

  return (
    <header className="sticky top-0 z-50 w-full border-b bg-background/80 backdrop-blur-md">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div className="flex items-center gap-3">
          <img src="/sikedul1.png" alt="SIKEDUL" className="h-12 w-12 object-contain" />
          <span className="text-2xl font-bold tracking-tight text-foreground">SIKEDUL</span>
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
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-border bg-card"
                aria-label="Pilih tema"
              >
                <Sun className="h-4 w-4" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40 rounded-2xl p-2">
              <DropdownMenuItem onClick={() => updateAppearance("light")} className="rounded-xl px-3 py-2 text-base">
                Light {appearance === "light" ? "✓" : ""}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => updateAppearance("dark")} className="rounded-xl px-3 py-2 text-base">
                Dark {appearance === "dark" ? "✓" : ""}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => updateAppearance("system")} className="rounded-xl px-3 py-2 text-base">
                System {appearance === "system" ? "✓" : ""}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </nav>
      </div>
    </header>
  )
}
