import { Navigate, Outlet } from "react-router-dom";

export default function Protected() {
  const token = localStorage.getItem("auth_token");
  const user = localStorage.getItem("auth_user");
  

  if (!token || !user) {
    return <Navigate to="/login" replace />;
  }

  return <Outlet />;
}
