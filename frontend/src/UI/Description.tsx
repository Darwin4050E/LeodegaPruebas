import { Card } from "@/components/ui/card";

function Description({ children }) {
  return (
    <Card className="p-6 shadow-sm">
      <h3 className="font-semibold mb-2">Descripción</h3>
      <p className="text-sm text-gray-600 leading-relaxed">{children}</p>
    </Card>
  );
}