import type { Metadata } from "next";
import type { ReactNode } from "react";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: "Saga MeetSpace | Portal Karyawan",
  description: "Portal karyawan untuk pemesanan ruang rapat Saga MeetSpace.",
};

/**
 * Audit 496 Frontend Parameter 3.6: Guard auth di server
 * Root layout untuk Saga MeetSpace portal. Server-side redirect dilindungi di middleware.ts
 * dan layout masing-masing rute terproteksi (/dashboard/layout.tsx, /admin/layout.tsx).
 */
export default function RootLayout({
  children,
}: Readonly<{
  children: ReactNode;
}>) {
  return (
    <html
      lang="id"
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col">{children}</body>
    </html>
  );
}
