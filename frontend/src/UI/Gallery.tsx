import { motion } from "framer-motion";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";



function Gallery({ images }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4 }}
      className="grid grid-cols-3 gap-2 h-80 md:h-96"
    >
      <Card className="col-span-2 overflow-hidden relative">
        <img
          src={images[0]}
          alt="main"
          className="object-cover w-full h-full"
        />
        <Badge className="absolute left-3 top-3 bg-white text-gray-700 shadow">
          Galería
        </Badge>
      </Card>
      <div className="flex flex-col gap-2">
        <Card className="h-1/2 overflow-hidden">
          <img
            src={images[1]}
            alt="img1"
            className="object-cover w-full h-full"
          />
        </Card>
        <Card className="h-1/2 overflow-hidden">
          <img
            src={images[2]}
            alt="img2"
            className="object-cover w-full h-full"
          />
        </Card>
      </div>
    </motion.div>
  );
}