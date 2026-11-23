-- Jalankan sekali pada database AeroBook yang sudah terpasang.
-- Mengizinkan satu kode booking memiliki beberapa detail tiket.
ALTER TABLE detail_pemesanan
    DROP INDEX uq_one_ticket_per_booking,
    ADD UNIQUE KEY uq_booking_seat (id_pemesanan, id_kursi_penerbangan);
