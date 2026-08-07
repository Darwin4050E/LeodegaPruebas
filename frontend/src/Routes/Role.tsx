import { Outlet } from "react-router-dom";

type Props = {
  allowed: Array<"admin" | "landlord">;
};

export default function Role({ allowed }: Props) {
  const token = localStorage.getItem("auth_token");
  const rawUser = localStorage.getItem("auth_user");

  if (!token || !rawUser) {
    return null; 
  }

  let user: any;
  try {
    user = JSON.parse(rawUser);
  } catch {
    return null;
  }

  const role = user?.role;

  if (!allowed.includes(role)) {
    return (
      <div className="p-6 text-center text-red-600 font-semibold">
        No tienes permisos para acceder a esta sección
      </div>
    );
  }

  return <Outlet />;
}
