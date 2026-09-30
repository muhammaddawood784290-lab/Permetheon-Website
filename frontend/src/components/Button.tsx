import { Link } from "react-router-dom";
import type { ComponentProps, ReactNode } from "react";

type Variant = "primary" | "secondary" | "ghost";
type CommonProps = {
  variant?: Variant;
  children: ReactNode;
  className?: string;
  dark?: boolean;
};

const base =
  "inline-flex items-center justify-center gap-2 rounded-pill px-6 py-3 text-sm font-semibold tracking-wide transition-all duration-200 ease-(--ease-intent) focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50";

const styles: Record<Variant, (dark: boolean) => string> = {
  primary: (dark) =>
    dark
      ? "bg-paper text-ink hover:bg-white hover:-translate-y-0.5 active:translate-y-0 shadow-lg shadow-black/20"
      : "bg-accent text-white hover:bg-accent-strong hover:-translate-y-0.5 active:translate-y-0 shadow-md shadow-accent/25",
  secondary: (dark) =>
    dark
      ? "border border-line-dark text-paper hover:border-fog-dark hover:bg-white/5"
      : "border border-line text-ink hover:border-fog hover:bg-paper-soft",
  ghost: (dark) =>
    dark ? "text-fog-dark hover:text-paper" : "text-fog hover:text-ink",
};

type ButtonAsButton = CommonProps & ComponentProps<"button">;
type ButtonAsLink = CommonProps & { href: string } & Omit<
    ComponentProps<typeof Link>,
    "to" | "children" | "className"
  >;

type ButtonProps = ButtonAsButton | ButtonAsLink;

function classesFor({
  variant = "primary",
  dark = false,
  className = "",
}: {
  variant?: Variant;
  dark?: boolean;
  className?: string;
}) {
  return `${base} ${styles[variant](dark)} ${className}`;
}

export function Button(props: ButtonProps) {
  if ("href" in props) {
    const isExternal = /^https?:\/\//.test(props.href);
    // Destructure ALL Button-only props out — spreading them through <Link>
    // leaks them onto the rendered <a> (dev warning: non-boolean attribute
    // `dark`) and any incoming className would clobber classesFor().
    const { href, variant, dark, className, children, ...linkRest } = props;
    if (isExternal) {
      return (
        <a
          href={href}
          target="_blank"
          rel="noopener noreferrer"
          className={classesFor({ variant, dark, className })}
        >
          {children}
        </a>
      );
    }
    return (
      <Link to={href} className={classesFor({ variant, dark, className })} {...linkRest}>
        {children}
      </Link>
    );
  }
  const { variant, dark, className, children, ...rest } = props;
  return (
    <button className={classesFor({ variant, dark, className })} {...rest}>
      {children}
    </button>
  );
}
