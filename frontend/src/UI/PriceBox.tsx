import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

function PriceBox() {
  return (
    <Card className="p-6 shadow-sm">
      <CardContent className="flex items-center justify-between p-0">
        <div>
          <p className="text-purple-600 font-bold text-3xl">$425</p>
          <p className="text-xs text-gray-500 -mt-1">USD mensual</p>
          <p className="text-gray-600 text-sm mt-1">300 m² • Altura 6 metros</p>
        </div>
        <div className="flex gap-3">
          <Button variant="default" className="bg-orange-500 hover:bg-orange-600">
            Imprimir
          </Button>
          <Button variant="default" className="bg-yellow-500 hover:bg-yellow-600">
            Reservar
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}