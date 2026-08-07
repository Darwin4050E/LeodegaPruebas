import React, { useState, useEffect } from 'react';
import { User, Bell, Lock, CreditCard, Globe, Shield, Mail, Phone, Menu } from 'lucide-react';
import api from '../api/axios';
import { useNavigate } from "react-router-dom";


const Settings: React.FC = () => {
    const [activeTab, setActiveTab] = useState('perfil');
    const [showMobileMenu, setShowMobileMenu] = useState(false);
    const [notificaciones, setNotificaciones] = useState({
        emailReservas: true,
        emailMensajes: true,
        pushReservas: true,
        pushMensajes: false,
        emailReportes: true,
    });

    const tabs = [
        { id: 'perfil', nombre: 'Perfil', icono: User },
        { id: 'notificaciones', nombre: 'Notificaciones', icono: Bell },
        { id: 'seguridad', nombre: 'Seguridad', icono: Lock },
        { id: 'pagos', nombre: 'Pagos', icono: CreditCard },
        { id: 'privacidad', nombre: 'Privacidad', icono: Shield },
    ];

    // Profile
    const [perfil, setPerfil] = useState({
        name: "",
        lastname: "",
        email: "",
        phone: "",
    });

    const [profileMsg, setProfileMsg] = useState<{ type: "success" | "error" | ""; text: string }>({
        type: "",
        text: "",
    });
    const [profileErrors, setProfileErrors] = useState<{ email?: string }>({});

    useEffect(() => {
        const load = async () => {
            try {
                const { data } = await api.get("/profile");
                setPerfil({
                    name: data.name ?? "",
                    lastname: data.lastname ?? "",
                    email: data.email ?? "",
                    phone: data.phone ?? "",
                });
            } catch (error) {
                console.error("Error cargando perfil:", error);
                setProfileMsg({ type: "error", text: "No se pudo cargar el perfil." });
            }
        };

        load();
    }, []);

    const [loading, setLoading] = useState(false);
    const handleGuardar = async () => {
        try {
            setLoading(true);
            setProfileErrors({});
            setProfileMsg({ type: "", text: "" });

            await api.put("/profile", {
                name: perfil.name,
                lastname: perfil.lastname,
                email: perfil.email,
                phone: perfil.phone,
            });

            setProfileMsg({ type: "success", text: "Perfil actualizado con éxito." });
        } catch (error: any) {
            const data = error?.response?.data;

            const emailError = data?.errors?.email?.[0];

            setProfileErrors({
                email: emailError,
            });

        } finally {
            setLoading(false);
        }
    };

    // SEGURIDAD

    const [passwordF, setPasswordF] = useState({
        actual_p: "",
        new_p: "",
        new_p_c: "",
    });
    const [passMsg, setPassMsg] = useState<string>("");
    const [passErrors, setPassErrors] = useState<{
        actual_p?: string;
        new_p?: string;
        new_p_c?: string;
    }>({});


    const [passLoading, setPassLoading] = useState(false);

    const handleActualizarPassword = async () => {
        setPassMsg("");
        setPassErrors({});
        try {
            setPassLoading(true);
            console.log("ENVIANDO:", passwordF);

            await api.put("/password", passwordF);
            setPasswordF({
                actual_p: "",
                new_p: "",
                new_p_c: "",
            });
        } catch (error: any) {
            const data = error?.response?.data;
            const errs = data?.errors || {};
            setPassErrors({
                actual_p: errs.actual_p?.[0],
                new_p: errs.new_p?.[0],
                new_p_c: errs.new_p_c?.[0],
            });
        } finally {
            setPassLoading(false);
        }
    };

    // SESIONES ACTIVAS 
    const [sessions, setSessions] = useState<any[]>([]);
    const [sessionsLoading, setSessionsLoading] = useState(false);
    const [sessionsMsg, setSessionsMsg] = useState<{ type: "success" | "error" | ""; text: string }>({
        type: "",
        text: "",
    });
    const handleSession = async () => {
        try {
            setSessionsLoading(true);
            setSessionsMsg({ type: "", text: "" });
            const { data } = await api.get("/sessions");
            setSessions(data.sessions ?? []);
        } catch (error) {
            console.error("Error cargando sesiones:", error);
        } finally {
            setSessionsLoading(false);
        }
    };

    useEffect(() => {
        if (activeTab === "seguridad") {
            handleSession();
        }
    }, [activeTab]);

    const cerrarSesion = async (tokenId: number) => {
        try {
            setSessionsMsg({ type: "", text: "" });
            await api.delete(`/sessions/${tokenId}`);
            setSessionsMsg({ type: "success", text: "Sesión cerrada." });
            await handleSession();
        } catch (error: any) {
            console.error("Error cerrando sesión:", error);

        }
    };

    const getDeviceLabel = (ua?: string) => {
        if (!ua) return "Dispositivo desconocido";
        const base = ua.split("(")[0].trim();
        return base || "Dispositivo";
    };

    const getLastUsedLabel = (s: any) => {
        if (s.is_current) return "Ahora";
        if (s.last_used_at) return `Último uso: ${s.last_used_at}`;
        return "Sin información de uso";
    };
    const navigate = useNavigate();
    const handleBorrarCuenta = async () => {
        const ok = window.confirm("¿Seguro? Esta acción es permanente y no se puede deshacer.");
        if (!ok) return;

        try {
            await api.delete("/account");
            localStorage.clear();
            alert("Cuenta eliminada correctamente");
            navigate("/login");
        } catch (e: any) {
            console.error(e);
            alert(e?.response?.data?.message || "No se pudo eliminar la cuenta");
        }
    };
    








    return (
        <div className="px-4 lg:pl-8 lg:pr-8 pt-5 bg-[#f5f6fa] min-h-screen">
            <div className="mb-6 mt-3">
                <h1 className="text-2xl font-semibold text-gray-900">Configuración</h1>
                <p className="text-gray-600 mt-1">Administra tu cuenta y preferencias</p>
            </div>

            <div className="lg:hidden mb-4">
                <button
                    onClick={() => setShowMobileMenu(!showMobileMenu)}
                    className="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 w-full justify-center"
                >
                    <Menu size={16} />
                    {showMobileMenu ? 'Ocultar Menú' : 'Mostrar Menú'}
                </button>
            </div>

            <div className="flex flex-col lg:flex-row gap-6">
                <div className={`
                    ${showMobileMenu ? 'block' : 'hidden lg:block'}
                    w-full lg:w-64 bg-white rounded-lg shadow-sm p-4
                `}>
                    <nav className="space-y-2">
                        {tabs.map((tab) => {
                            const Icon = tab.icono;
                            return (
                                <button
                                    key={tab.id}
                                    onClick={() => {
                                        setActiveTab(tab.id);
                                        if (window.innerWidth < 1024) {
                                            setShowMobileMenu(false);
                                        }
                                    }}
                                    className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${activeTab === tab.id
                                        ? 'bg-purple-50 text-purple-600 font-medium'
                                        : 'text-gray-700 hover:bg-gray-50'
                                        }`}
                                >
                                    <Icon size={20} />
                                    <span>{tab.nombre}</span>
                                </button>
                            );
                        })}
                    </nav>
                </div>

                <div className="flex-1 bg-white rounded-lg shadow-sm p-4 lg:p-6">
                    {activeTab === 'perfil' && (
                        <div className="space-y-6">
                            <div>
                                <h2 className="text-xl font-semibold text-gray-900 mb-4">Información Personal</h2>



                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div>
                                        <label htmlFor="nombre" className="block text-sm font-medium text-gray-700 mb-2">
                                            Nombre
                                        </label>
                                        <input
                                            type="text"
                                            value={perfil.name}
                                            onChange={(e) => setPerfil({ ...perfil, name: e.target.value })}
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm lg:text-base"
                                        />
                                    </div>
                                    <div>
                                        <label htmlFor="apellido" className="block text-sm font-medium text-gray-700 mb-2">
                                            Apellido
                                        </label>
                                        <input
                                            type="text"
                                            value={perfil.lastname}
                                            onChange={(e) => setPerfil({ ...perfil, lastname: e.target.value })}
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm lg:text-base"
                                        />
                                    </div>
                                    <div className="lg:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            <Mail size={16} className="inline mr-2" />
                                            Correo Electrónico
                                        </label>
                                        <input
                                            type="email"
                                            value={perfil.email}
                                            onChange={(e) => {
                                                setPerfil({ ...perfil, email: e.target.value });
                                                setProfileErrors((prev) => ({ ...prev, email: undefined }));
                                                setProfileMsg({ type: "", text: "" });
                                            }}
                                            className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent ${profileErrors.email ? "border-red-500" : "border-gray-300"
                                                }`}
                                        />
                                        {profileErrors.email && (
                                            <p className="mt-1 text-xs text-red-600">{profileErrors.email}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            <Phone size={16} className="inline mr-2" />
                                            Teléfono
                                        </label>
                                        <input
                                            type="tel"
                                            value={perfil.phone}
                                            onChange={(e) => setPerfil({ ...perfil, phone: e.target.value })}
                                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm lg:text-base"
                                        />
                                    </div>

                                </div>

                                <div> {profileMsg.text && (
                                    <p
                                        className={`mt-3 text-sm font-medium ${profileMsg.type === "success"
                                            ? "text-green-600"
                                            : "text-red-600"
                                            }`}
                                    >
                                        {profileMsg.text}
                                    </p>
                                )}  </div>


                                <div className="flex flex-col sm:flex-row gap-3 mt-6">

                                    <button onClick={handleGuardar}
                                        disabled={loading}
                                        className="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
                                    >
                                        {loading ? "Guardando..." : "Guardar Cambios"}
                                    </button>

                                    <button
                                        type="button"
                                        className="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium text-sm lg:text-base"
                                        onClick={() => {
                                            setProfileMsg({ type: "", text: "" });
                                            setProfileErrors({});
                                        }}
                                    >
                                        Cancelar
                                    </button>


                                </div>
                            </div>
                        </div>
                    )}

                    {activeTab === 'notificaciones' && (
                        <div className="space-y-6">
                            <div>
                                <h2 className="text-xl font-semibold text-gray-900 mb-4">Preferencias de Notificaciones</h2>
                                <p className="text-gray-600 mb-6">Elige cómo quieres recibir notificaciones</p>

                                <div className="space-y-4">
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between py-4 border-b gap-3">
                                        <div className="flex-1">
                                            <h3 className="font-medium text-gray-900 text-sm lg:text-base">Notificaciones de Reservas por Email</h3>
                                            <p className="text-sm text-gray-500">Recibe emails cuando alguien reserva tu bodega</p>
                                        </div>
                                        <label className="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={notificaciones.emailReservas}
                                                onChange={(e) => setNotificaciones({ ...notificaciones, emailReservas: e.target.checked })}
                                                className="sr-only peer"
                                            />
                                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between py-4 border-b gap-3">
                                        <div className="flex-1">
                                            <h3 className="font-medium text-gray-900 text-sm lg:text-base">Notificaciones Push de Reservas</h3>
                                            <p className="text-sm text-gray-500">Recibe notificaciones push instantáneas</p>
                                        </div>
                                        <label className="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={notificaciones.pushReservas}
                                                onChange={(e) => setNotificaciones({ ...notificaciones, pushReservas: e.target.checked })}
                                                className="sr-only peer"
                                            />
                                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between py-4 border-b gap-3">
                                        <div className="flex-1">
                                            <h3 className="font-medium text-gray-900 text-sm lg:text-base">Mensajes de Usuarios por Email</h3>
                                            <p className="text-sm text-gray-500">Recibe emails cuando te envíen mensajes</p>
                                        </div>
                                        <label className="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={notificaciones.emailMensajes}
                                                onChange={(e) => setNotificaciones({ ...notificaciones, emailMensajes: e.target.checked })}
                                                className="sr-only peer"
                                            />
                                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>

                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between py-4 border-b gap-3">
                                        <div className="flex-1">
                                            <h3 className="font-medium text-gray-900 text-sm lg:text-base">Reportes Mensuales</h3>
                                            <p className="text-sm text-gray-500">Resumen de tus bodegas y ganancias</p>
                                        </div>
                                        <label className="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={notificaciones.emailReportes}
                                                onChange={(e) => setNotificaciones({ ...notificaciones, emailReportes: e.target.checked })}
                                                className="sr-only peer"
                                            />
                                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}



                    {activeTab === 'seguridad' && ( // SEGURIDAD
                        <div className="space-y-6">
                            <div>
                                <h2 className="text-xl font-semibold text-gray-900 mb-4">Seguridad de la Cuenta</h2>

                                <div className="space-y-6">
                                    <div className="border rounded-lg p-4">
                                        <h3 className="font-medium text-gray-900 mb-4">Cambiar Contraseña</h3>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    Contraseña Actual
                                                </label>
                                                <input
                                                    type="password"
                                                    value={passwordF.actual_p}
                                                    onChange={(e) => setPasswordF({ ...passwordF, actual_p: e.target.value })}
                                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm lg:text-base"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    Nueva Contraseña
                                                </label>
                                                <input
                                                    type="password"
                                                    value={passwordF.new_p}
                                                    onChange={(e) => setPasswordF({ ...passwordF, new_p: e.target.value })}
                                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm lg:text-base"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    Confirmar Nueva Contraseña
                                                </label>
                                                <input
                                                    type="password"
                                                    value={passwordF.new_p_c}
                                                    onChange={(e) => setPasswordF({ ...passwordF, new_p_c: e.target.value })}
                                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm lg:text-base"
                                                />
                                            </div>
                                            <button
                                                onClick={handleActualizarPassword}
                                                disabled={passLoading}
                                                className={`px-6 py-2 text-white rounded-lg font-medium text-sm lg:text-base ${passLoading ? "bg-purple-300 cursor-not-allowed" : "bg-purple-600 hover:bg-purple-700"
                                                    }`}
                                            >
                                                {passLoading ? "Actualizando..." : "Actualizar Contraseña"}
                                            </button>
                                            {passMsg && (
                                                <p className="mt-3 text-sm font-medium text-red-600">
                                                    {passMsg}
                                                </p>
                                            )}
                                        </div>
                                    </div>


                                    <div className="border rounded-lg p-4">
                                        <div className="flex items-center justify-between gap-3 mb-4">
                                            <h3 className="font-medium text-gray-900">Sesiones Activas</h3>


                                        </div>
                                        {sessionsLoading ? (
                                            <p className="text-sm text-gray-500">Cargando sesiones...</p>
                                        ) : sessions.length === 0 ? (
                                            <p className="text-sm text-gray-500">No hay sesiones registradas.</p>
                                        ) : (
                                            <div className="space-y-3">
                                                {sessions.map((s) => (
                                                    <div
                                                        key={s.id}
                                                        className="flex flex-col sm:flex-row sm:items-center justify-between py-2 gap-2 border-b last:border-b-0"
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <Globe size={20} className="text-gray-400" />
                                                            <div>
                                                                <p className="font-medium text-gray-900 text-sm lg:text-base">
                                                                    {getDeviceLabel(s.user_agent)}
                                                                </p>
                                                                <p className="text-sm text-gray-500">
                                                                    {s.ip_address ?? "IP desconocida"} • {getLastUsedLabel(s)}
                                                                </p>
                                                            </div>
                                                        </div>

                                                        {s.is_current ? (
                                                            <span className="text-sm text-green-600 font-medium">Actual</span>
                                                        ) : (
                                                            <button
                                                                onClick={() => cerrarSesion(s.id)}
                                                                className="text-sm text-red-600 font-medium hover:underline"
                                                            >
                                                                Cerrar
                                                            </button>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        {sessionsMsg.text && (
                                            <p
                                                className={`mt-3 text-sm font-medium ${sessionsMsg.type === "success" ? "text-green-600" : "text-red-600"
                                                    }`}
                                            >
                                                {sessionsMsg.text}
                                            </p>
                                        )}
                                    </div>

                                </div>
                            </div>
                        </div>
                    )}

                    {activeTab === 'pagos' && (
                        <div className="space-y-6">
                            <div>
                                <h2 className="text-xl font-semibold text-gray-900 mb-4">Métodos de Pago</h2>
                                <p className="text-gray-600 mb-6">Administra cómo recibes tus pagos</p>

                                <button className="mb-6 px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium text-sm lg:text-base w-full sm:w-auto">
                                    + Agregar Cuenta Bancaria
                                </button>

                                <div className="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                    <CreditCard size={48} className="mx-auto text-gray-400 mb-3" />
                                    <p className="text-gray-600">No tienes métodos de pago configurados</p>
                                    <p className="text-sm text-gray-500 mt-1">Agrega una cuenta bancaria para recibir pagos</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {activeTab === 'privacidad' && (    // PRIVACIDAD Y DATOSSSSS
                        <div className="space-y-6">
                            <div>
                                <h2 className="text-xl font-semibold text-gray-900 mb-4">Privacidad y Datos</h2>

                                <div className="space-y-4">
                                    <div className="border rounded-lg p-4">
                                        <h3 className="font-medium text-gray-900 mb-2">Visibilidad del Perfil</h3>
                                        <p className="text-sm text-gray-500 mb-4">Controla quién puede ver tu información</p>
                                        <select className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm lg:text-base">
                                            <option>Público</option>
                                            <option>Solo usuarios registrados</option>
                                            <option>Privado</option>
                                        </select>
                                    </div>  
                                    
                                    {/* SE NECESITA UNA LIBRERIA PARA ESTO
                                    <div className="border rounded-lg p-4">
                                        <h3 className="font-medium text-gray-900 mb-2">Descargar mis Datos</h3>
                                        <p className="text-sm text-gray-500 mb-4">Obtén una copia de tu información</p>
                                        <button className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium text-sm lg:text-base w-full sm:w-auto">
                                            Solicitar Datos
                                        </button>
                                    </div>
                                    */}

                                    <div className="border border-red-200 rounded-lg p-4 bg-red-50">
                                        <h3 className="font-medium text-red-900 mb-2">Eliminar Cuenta</h3>
                                        <p className="text-sm text-red-600 mb-4">Esta acción es permanente y no se puede deshacer</p>
                                        <button
                                            onClick={handleBorrarCuenta}
                                            className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium text-sm lg:text-base w-full sm:w-auto"
                                        >
                                            Eliminar Cuenta
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default Settings;