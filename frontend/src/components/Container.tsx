import type { ReactNode } from "react";

type ContainerProps = {
  children: ReactNode;
  size?: "default" | "wide" | "prose";
  className?: string;
};

const sizeMap: Record<NonNullable<ContainerProps["size"]>, string> = {
  default: "max-w-(--container-default)",
  wide: "max-w-(--container-wide)",
  prose: "max-w-(--container-prose)",
};

export function Container({ children, size = "default", className = "" }: ContainerProps) {
  return (
    <div className={`mx-auto w-full px-5 sm:px-8 ${sizeMap[size]} ${className}`}>
      {children}
    </div>
  );
}
