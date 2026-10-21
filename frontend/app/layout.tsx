import type { Metadata } from "next";
import "./globals.css";
export const metadata: Metadata = { title: "CityPulse | Integration Project", description: "A secure city weather dashboard project" };
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { return <html lang="en"><body>{children}</body></html>; }
