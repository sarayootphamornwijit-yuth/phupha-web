// vehicle_model.dart
// โมเดลข้อมูลยานพาหนะของกิจกรรม
// สอดคล้องกับคอลัมน์ในตาราง `vehicles`

class VehicleModel {
  final int vehicleId;
  final int activityId;
  final String vehicleType;
  final String licensePlate;
  final String driverName;
  final String? driverPhone;
  final int capacity;
  final int bookedSeats;
  final int availableSeats;

  VehicleModel({
    required this.vehicleId,
    required this.activityId,
    required this.vehicleType,
    required this.licensePlate,
    required this.driverName,
    this.driverPhone,
    required this.capacity,
    this.bookedSeats = 0,
    this.availableSeats = 0,
  });

  // แปลงข้อมูลจาก JSON ของ API เป็น VehicleModel Object
  factory VehicleModel.fromJson(Map<String, dynamic> json) {
    final cap = int.tryParse(json['capacity']?.toString() ?? '0') ?? 0;
    final booked = int.tryParse(json['booked_seats']?.toString() ?? '0') ?? 0;
    var avail = int.tryParse(json['available_seats']?.toString() ?? '') ?? (cap - booked);
    if (avail < 0) avail = 0;

    return VehicleModel(
      vehicleId: int.tryParse(json['vehicle_id']?.toString() ?? '0') ?? 0,
      activityId: int.tryParse(json['activity_id']?.toString() ?? '0') ?? 0,
      vehicleType: json['vehicle_type']?.toString() ?? '',
      licensePlate: json['license_plate']?.toString() ?? '',
      driverName: json['driver_name']?.toString() ?? '',
      driverPhone: json['driver_phone']?.toString(),
      capacity: cap,
      bookedSeats: booked,
      availableSeats: avail,
    );
  }
}
