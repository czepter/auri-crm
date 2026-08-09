declare namespace App {
namespace Data {
export type StaffProfileData = {
id: string,
name: string,
email: string,
role: string,
mfaConfirmed: boolean,
};
}
namespace Enums {
export type StaffRole = 'owner' | 'photographer' | 'second_shooter' | 'editor' | 'assistant';
}
}
