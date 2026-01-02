import { Calendar, Bell, CheckSquare, ShieldCheck } from "lucide-react"

const features = [
  {
    name: "Kalender Interaktif",
    description: "Visualisasikan jadwal harian, mingguan, dan bulanan Anda dengan FullCalendar yang responsif.",
    icon: Calendar,
    color: "bg-blue-500/10 text-blue-500",
  },
  {
    name: "Pengingat Otomatis",
    description: "Jangan pernah melewatkan acara penting dengan sistem notifikasi real-time yang dapat disesuaikan.",
    icon: Bell,
    color: "bg-amber-500/10 text-amber-500",
  },
  {
    name: "Daftar Tugas Terpadu",
    description: "Kelola prioritas Anda dengan Todo List yang terintegrasi langsung ke dalam kalender.",
    icon: CheckSquare,
    color: "bg-emerald-500/10 text-emerald-500",
  },
  {
    name: "Keamanan Data",
    description: "Data Anda dienkripsi dan diisolasi sepenuhnya, memastikan privasi jadwal Anda tetap terjaga.",
    icon: ShieldCheck,
    color: "bg-purple-500/10 text-purple-500",
  },
]

export default function FeatureSection() {
  return (
    <section className="bg-secondary/30 py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mb-16 text-center">
          <h2 className="mb-4 text-3xl font-bold text-foreground sm:text-4xl">Fitur Unggulan SIKEDUL</h2>
          <p className="mx-auto max-w-2xl text-lg text-muted-foreground">
            Kami menyediakan alat yang Anda butuhkan untuk mengelola waktu dengan lebih efisien dan terorganisir.
          </p>
        </div>
        <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
          {features.map((feature) => (
            <div
              key={feature.name}
              className="group relative rounded-2xl border bg-card p-8 transition-all hover:-translate-y-1 hover:shadow-xl"
            >
              <div className={`mb-6 flex h-12 w-12 items-center justify-center rounded-xl ${feature.color}`}>
                <feature.icon className="h-6 w-6" />
              </div>
              <h3 className="mb-3 text-xl font-semibold text-foreground">{feature.name}</h3>
              <p className="text-muted-foreground">{feature.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
